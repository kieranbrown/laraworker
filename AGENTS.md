# Agent Development Guide

This document provides guidance for AI agents working on the laraworker project.

## Important: Do NOT Deploy to laraworker.kswb.dev

The demo site at `https://laraworker.kswb.dev` is the **showcase marketing site** deployed automatically via CI from the `demo/` folder. **Do not deploy to it directly.** Agents should create their own ephemeral playground to test changes locally using the scripts below.

## Project Structure

- `demo/` — Showcase marketing site (Inertia + Vue + Tailwind). Deployed to `laraworker.kswb.dev` via CI on push to main. Do not modify unless working on the demo site itself.
- `playground/` — Ephemeral test project created by `scripts/playground-setup.sh`. Gitignored. Use this to test package changes.

## Quick Local Iteration Workflow (Fast Feedback)

For rapid development and testing, use local wrangler commands instead of the slow CI loop (push → CI build → deploy → test).

### Local Development Server (Instant Feedback)

Set up an ephemeral playground, then start the local dev server:

```bash
# First-time setup (creates playground/ from a fresh Laravel project)
scripts/playground-setup.sh --force

# Build and start local dev server
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

**IMPORTANT:** `wrangler dev` uses miniflare locally. For WASM-specific behavior (memory, OPcache, isolate lifecycle), always verify with `wrangler deploy` against the real Cloudflare runtime. Local miniflare may behave differently.

### Recommended Iteration Loop

1. **Iterate locally** with `wrangler dev` (fast, catches most issues)
2. **Run smoke tests** with `scripts/playground-smoke-test.sh` for automated verification
3. **Push to git** and let CI run as final validation (slow, but confirms everything)

### Artisan Commands Available

- `php artisan laraworker:dev` — Builds app + starts `wrangler dev` locally
- `php artisan laraworker:build` — Just builds (generates `.laraworker/` directory)
- `php artisan laraworker:deploy` — Builds + runs `wrangler deploy` (direct deploy, no CI)

## Performance Testing

### OPcache Diagnostic Endpoint

The worker exposes `/__opcache-status` when `OPCACHE_DEBUG=true` is set as a Worker env var. Use it to verify OPcache is actually caching opcodes.

```bash
# After starting local dev server, make two sequential requests:
curl -s http://localhost:8787/__opcache-status | jq .

# First request:  opcache_statistics.hits should be 0, misses > 0
# Second request: hits should increase — proves OPcache persists between requests
# If hits stays 0 on subsequent requests, OPcache is NOT persisting.
```

### Timing Requests

```bash
# Warm request timing (run multiple times, ignore the first cold start):
for i in {1..5}; do
  curl -s -o /dev/null -w "Request $i: %{time_total}s\n" http://localhost:8787/
done

# Expected with working OPcache:
#   Request 1: ~0.5-2.5s (cold start or OPcache cold miss)
#   Request 2+: <0.1-0.2s (OPcache hot — cached opcodes)
```

### Health Check (No PHP)

```bash
# Responds without initializing PHP — baseline worker overhead:
curl -s -o /dev/null -w "%{time_total}s\n" http://localhost:8787/__health
```

## Rebuilding the PHP WASM Binary

When modifying PHP internals (CGI SAPI patches, extension changes, php.ini defaults), you must rebuild the WASM binary.

### Prerequisites

- Docker (OrbStack, Rancher, or Docker Desktop)
- The builder image `seanmorris/phpwasm-emscripten-builder:latest` (linux/amd64, ~2.6 GB). **This image is NOT publicly pullable from Docker Hub.** It must already be cached locally. Verify with:
  ```bash
  docker images | grep phpwasm-emscripten-builder
  ```
  If missing, it can be built from the `emscripten-builder.dockerfile` in the `seanmorris/php-wasm` sm-8.5 branch.

#### Apple Silicon (arm64) — Rosetta Required

The builder image is x86_64 only. On Apple Silicon Macs, Docker runs it via Rosetta emulation.

**OrbStack:** Enable Rosetta in Settings > General > "Use Rosetta". This is usually enabled by default.

**Docker Desktop:** Enable Rosetta in Settings > General > "Use Rosetta for x86_64/amd64 emulation on Apple Silicon".

**Verify Rosetta works:**
```bash
docker run --rm --platform linux/amd64 seanmorris/phpwasm-emscripten-builder:latest echo "Rosetta works"
```

If you get `rosetta error: Rosetta is only intended to run on Apple Silicon...`, enable Rosetta in your Docker runtime settings.

### Build Process

```bash
cd php-wasm-build && ./build.sh
```

**CRITICAL: Run from the host machine, NOT inside a Docker container.** The Makefile uses `docker compose` to spawn containers for each build step. Running inside Docker causes Docker-in-Docker recursion.

**Build time: 20-60 minutes** (longer on Apple Silicon due to Rosetta emulation). The script:
1. Clones `seanmorris/php-wasm` sm-8.5 branch into `/tmp/php-wasm-build-XXXXXX`
2. Copies `.php-wasm-rc` (build config) and patches from `patches/`
3. Injects patch scripts and configure flags into the Makefile
4. Runs `make worker-cgi-mjs` — Makefile spawns Docker containers via `docker compose` for each compilation step
5. Checks output binary size against 4 MB gzipped budget
6. Copies output (`php8.5-cgi-worker.mjs` + `.wasm` + helper `.mjs` modules) back to `php-wasm-build/`
7. Cleans up the temp directory

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
```

### Verifying the Build

After a successful build, verify the output:

```bash
# Check binary exists and size is reasonable
ls -lh php-wasm-build/php8.5-cgi-worker.mjs.wasm
# Expected: ~13-14 MB uncompressed

# Check gzipped size is within budget (< 4 MB)
gzip -c php-wasm-build/php8.5-cgi-worker.mjs.wasm | wc -c
# Expected: < 4194304 bytes

# Verify wasmTable export exists (required for ALLOW_TABLE_GROWTH persistent module)
grep -c 'wasmTable' php-wasm-build/php8.5-cgi-worker.mjs
# Expected: > 0 (table growth is a WASM-level property, not a named JS export)

# Full integration test: build playground and run sequential requests
scripts/playground-build.sh
cd playground/.laraworker && npx wrangler dev &
sleep 5
for i in {1..10}; do curl -s -o /dev/null -w "Request $i: %{time_total}s\n" http://localhost:8787/; done
# Request 1 should be slow (cold), requests 2+ should be fast (OPcache hot)
```

### Troubleshooting

**"Rosetta is only intended to run on Apple Silicon..."** — Enable Rosetta in your Docker runtime (OrbStack/Docker Desktop). See Prerequisites above.

**"pull access denied for seanmorris/phpwasm-emscripten-builder"** — The image is NOT on Docker Hub. It must already be cached in your local Docker. Check with `docker images | grep phpwasm-emscripten-builder`.

**Build hangs during `git clone`** — The php-wasm repo can be large. The clone uses `--depth 1 --single-branch` to minimize download. Ensure you have a stable internet connection.

**Docker-in-Docker errors** — You're running `build.sh` inside a container. Run it from the host machine directly.

**"table index is out of bounds" at runtime** — The WASM binary was built without `ALLOW_TABLE_GROWTH=1`. This flag is injected into `EXTRA_FLAGS` in `build.sh` (not `LDFLAGS`, which is unused by the upstream Makefile). Verify the sed injection in build.sh is working.

### Key Files

| File | Purpose |
|------|---------|
| `php-wasm-build/.php-wasm-rc` | Build flags (PHP version, OPTIMIZE, extensions, memory) |
| `php-wasm-build/patches/` | Custom patches applied to PHP source during build |
| `php-wasm-build/build.sh` | Orchestrates the full WASM build via Docker |
| `php-wasm-build/PhpCgiBase.mjs` | JS-side PHP CGI runtime (request lifecycle, SAPI calls) |
| `php-wasm-build/php8.5-cgi-worker.mjs` | Emscripten JS module (generated) |
| `php-wasm-build/php8.5-cgi-worker.mjs.wasm` | PHP WASM binary (generated, ~13 MB uncompressed) |

### PHP CGI SAPI Lifecycle (OPcache Persistence Fix)

`PhpCgiBase.mjs` calls these WASM-exported functions:

| Function | Called When | What It Does |
|----------|-----------|--------------|
| `pib_storage_init` | Once during `refresh()` | Initializes Emscripten FS mounts |
| `wasm_sapi_cgi_init` | Once during `refresh()` | Sets `USE_ZEND_ALLOC=0` |
| `wasm_sapi_cgi_putenv` | Before each request | Sets CGI env vars (REQUEST_URI, etc.) |
| `main` | Every request (but startup only once) | **First call:** full startup → execute → (shutdown guarded) <br>**Subsequent calls:** skip startup, handle request directly |

**The Fix:** The `cgi-persistent-module.sh` patch adds a static `_wasm_module_started` flag to `cgi_main.c`:
- First call to `main()`: runs full `php_module_startup()`, sets flag, handles request
- Subsequent calls: skips startup via if-guard, jumps directly to request handling
- `php_module_shutdown()` and `sapi_shutdown()` are guarded with `#ifndef __EMSCRIPTEN__` — they never run between requests
- This keeps OPcache's shared memory alive across all requests within an isolate

**Results:** Warm TTFB ~17ms locally, 781+ hits observed in production. OPcache now truly persists between requests.

**Note:** The patch uses an if-guard approach instead of goto (which confused Asyncify's stack transformation). See `php-wasm-build/patches/cgi-persistent-module.sh` for the full implementation.

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
- `demo/` — Showcase marketing site (Inertia + Vue + Tailwind), deployed to laraworker.kswb.dev via CI
- `php-wasm-build/` — Custom PHP WASM builder toolchain
- `php-wasm-build/patches/` — Patches applied to PHP source during WASM build
- `php-wasm-build/PhpCgiBase.mjs` — JS-side request lifecycle (calls WASM-exported functions)
- `tests/` — Pest test suite
- `.fuel/docs/performance-investigation.md` — Performance analysis and benchmarks
