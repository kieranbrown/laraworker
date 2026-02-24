# Agent Development Guide

This document provides guidance for AI agents working on the laraworker project.

## Quick Local Iteration Workflow (Fast Feedback)

For rapid development and testing, use local wrangler commands instead of the slow CI loop (push → CI build → deploy → test).

### Local Development Server (Instant Feedback)

Build once, then start the local dev server:

```bash
# From the playground (or any Laravel app with laraworker installed)
cd playground
php artisan laraworker:build
cd .laraworker && npx wrangler dev
```

The worker will be available at `http://localhost:8787`. Test with:

```bash
curl http://localhost:8787
```

**Key benefits:**
- No git push required
- No CI wait time (5-10 minutes → instant)
- Live reload on file changes

### Direct Deploy to Production

Cloudflare credentials are in `.env` (gitignored). Deploy directly:

```bash
cd playground/.laraworker
npx wrangler deploy
```

**Deploy time:** ~30 seconds. The demo site is at `https://laraworker.kswb.dev`.

**IMPORTANT:** `wrangler dev` uses miniflare locally. For WASM-specific behavior (memory, OPcache, isolate lifecycle), always verify with `wrangler deploy` against the real Cloudflare runtime. Local miniflare may behave differently.

### Live Production Logs

Monitor deployed workers in real-time:

```bash
cd playground/.laraworker
npx wrangler tail
```

### Recommended Iteration Loop

1. **Iterate locally** with `wrangler dev` (fast, catches most issues)
2. **Deploy directly** with `wrangler deploy` when ready (30s, real CF runtime)
3. **Push to git** and let CI run as final validation (slow, but confirms everything)

### Artisan Commands Available

- `php artisan laraworker:dev` — Builds app + starts `wrangler dev` locally
- `php artisan laraworker:build` — Just builds (generates `.laraworker/` directory)
- `php artisan laraworker:deploy` — Builds + runs `wrangler deploy` (direct deploy, no CI)

## Performance Testing

### OPcache Diagnostic Endpoint

The worker exposes `/__opcache-status` when `OPCACHE_DEBUG=true` is set as a Worker env var. Use it to verify OPcache is actually caching opcodes.

```bash
# After deploying, make two sequential requests:
curl -s https://laraworker.kswb.dev/__opcache-status | jq .

# First request:  opcache_statistics.hits should be 0, misses > 0
# Second request: hits should increase — proves OPcache persists between requests
# If hits stays 0 on subsequent requests, OPcache is NOT persisting.
```

### Timing Requests

```bash
# Warm request timing (run multiple times, ignore the first cold start):
for i in {1..5}; do
  curl -s -o /dev/null -w "Request $i: %{time_total}s\n" https://laraworker.kswb.dev/
done

# Expected with working OPcache:
#   Request 1: ~0.5-2.5s (cold start or OPcache cold miss)
#   Request 2+: <0.1-0.2s (OPcache hot — cached opcodes)
```

### Health Check (No PHP)

```bash
# Responds without initializing PHP — baseline worker overhead:
curl -s -o /dev/null -w "%{time_total}s\n" https://laraworker.kswb.dev/__health
```

## Rebuilding the PHP WASM Binary

When modifying PHP internals (CGI SAPI patches, extension changes, php.ini defaults), you must rebuild the WASM binary.

### Prerequisites

- Docker (OrbStack, Rancher, or Docker Desktop)
- The builder image: `docker pull seanmorris/phpwasm-emscripten-builder:latest`

### Build Process

```bash
cd php-wasm-build && ./build.sh
```

**Build time: 20-60 minutes.** The script:
1. Clones `seanmorris/php-wasm` sm-8.5 branch into a temp dir
2. Copies `.php-wasm-rc` (build config) and patches from `patches/`
3. Runs `make worker-cgi-mjs` via Docker (Emscripten cross-compilation)
4. Copies output (`php8.5-cgi-worker.mjs` + `.wasm`) back to `php-wasm-build/`

### Adding New Patches

Patches go in `php-wasm-build/patches/` as shell scripts. They are injected into the Makefile's patch flow and run inside Docker after the base php-wasm patches.

Example: `patches/opcache-wasm-support.sh` patches `cgi_main.c` to fix OPcache shared memory for Emscripten.

To add a new patch:
1. Create `patches/my-patch.sh` that modifies files under `third_party/php8.5-src/`
2. Add to `build.sh` after the existing patch injection (same `sed` pattern)
3. Rebuild with `./build.sh`

### After Rebuilding

```bash
# The new binary is in php-wasm-build/. Rebuild the playground to use it:
cd playground && php artisan laraworker:build

# Test locally:
cd .laraworker && npx wrangler dev

# Deploy to production:
npx wrangler deploy
```

### Key Files

| File | Purpose |
|------|---------|
| `php-wasm-build/.php-wasm-rc` | Build flags (PHP version, OPTIMIZE, extensions, memory) |
| `php-wasm-build/patches/` | Custom patches applied to PHP source during build |
| `php-wasm-build/build.sh` | Orchestrates the full WASM build via Docker |
| `php-wasm-build/PhpCgiBase.mjs` | JS-side PHP CGI runtime (request lifecycle, SAPI calls) |
| `php-wasm-build/php8.5-cgi-worker.mjs` | Emscripten JS module (generated) |
| `php-wasm-build/php8.5-cgi-worker.mjs.wasm` | PHP WASM binary (generated, ~13 MB uncompressed) |

### PHP CGI SAPI Lifecycle (Critical for OPcache)

`PhpCgiBase.mjs` calls these WASM-exported functions:

| Function | Called When | What It Does |
|----------|-----------|--------------|
| `pib_storage_init` | Once during `refresh()` | Initializes Emscripten FS mounts |
| `wasm_sapi_cgi_init` | Once during `refresh()` | Sets `USE_ZEND_ALLOC=0` (that's ALL it does) |
| `wasm_sapi_cgi_putenv` | Before each request | Sets CGI env vars (REQUEST_URI, etc.) |
| `main` | **Every request** | Full PHP CGI lifecycle: `php_module_startup()` → execute → `php_module_shutdown()` |

**CRITICAL:** Calling `main()` per request means `php_module_shutdown()` destroys OPcache SHM every time. OPcache compiles all files, caches them, then throws the cache away. This makes OPcache **actively harmful** in the current architecture.

The fix: patch `cgi_main.c` to do `php_module_startup()` once and only call `php_request_startup()`/`php_request_shutdown()` per request. See the seanmorris/php-wasm `patch/php8.5.patch` for context on how `cgi_main.c` is already patched.

## Testing Changes Locally

The playground is an ephemeral Laravel project used to test laraworker changes in a real environment.

### First-time setup

```bash
scripts/playground-setup.sh --force
```

This creates a fresh Laravel project in `playground/`, installs laraworker as a path repository, and runs the install command.

### After making changes to the package

When you modify laraworker code, you need to rebuild and test:

```bash
# Build the project for Cloudflare Workers
scripts/playground-build.sh

# Or run the full smoke test (builds + starts dev server + tests)
scripts/playground-smoke-test.sh
```

### Full verification

```bash
scripts/playground-smoke-test.sh
```

This script:
1. Builds the playground project
2. Starts the dev server in the background
3. Runs HTTP smoke tests against localhost:8787
4. Reports pass/fail status
5. Cleans up the dev server

### Teardown

```bash
scripts/playground-teardown.sh
```

Removes the playground directory entirely. Use this when you're done testing or need a clean slate.

## Common Code Locations

- `src/` — Main composer package source
- `src/Console/` — Artisan commands (InstallCommand.php, BuildCommand.php, DevCommand.php, DeployCommand.php, StatusCommand.php)
- `src/BuildDirectory.php` — Helper for `.laraworker/` directory operations
- `stubs/` — Worker stubs (worker.ts.stub, build-app.mjs, wrangler.jsonc.stub)
- `config/laraworker.php` — Package configuration (OPcache, extensions, build options)
- `scripts/` — Playground and testing scripts
- `php-wasm-build/` — Custom PHP WASM builder toolchain
- `php-wasm-build/patches/` — Patches applied to PHP source during WASM build
- `php-wasm-build/PhpCgiBase.mjs` — JS-side request lifecycle (calls WASM-exported functions)
- `tests/` — Pest test suite
- `.fuel/docs/performance-investigation.md` — Performance analysis and benchmarks
