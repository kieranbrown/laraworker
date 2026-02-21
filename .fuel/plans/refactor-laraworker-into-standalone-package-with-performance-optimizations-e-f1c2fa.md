# Epic: Refactor Laraworker into Standalone Package with Performance Optimizations

## Context

Laraworker runs Laravel on Cloudflare Workers via php-cgi-wasm. Currently deployed at https://laraworker.kswb.dev with:
- Custom PHP 8.2 WASM binary (9.8MB raw, ~2.6MB gzipped) — fits free tier 3MB limit
- app.tar.gz (3.5MB compressed) served via Static Assets
- Cold start ~2s, warm ~300-400ms
- Two PHP runtime modes: custom WASM build (MAIN_MODULE=0) and npm package approach (shared libs)

**Current repo structure**: Full Laravel app with `packages/laraworker/` as a path repo. Needs refactoring to be just the package.

## Key Constraints (Cloudflare Workers)
- **Free tier**: 3MB compressed worker, 10ms CPU per request
- **Paid tier**: 10MB compressed, 30s CPU (adjustable to 5min)
- **Memory**: 128MB per isolate (shared between JS heap + WASM)
- **Global scope**: Must complete in 1 second
- **Static assets**: Cloudflare edge serves automatically, no worker CPU used

## Phase 1: Package Refactoring (Foundation)

### Task 1.1: Restructure repo as standalone Composer package
**Current**: Root is a Laravel app, package lives in `packages/laraworker/`
**Target**: Root IS the package. Move `packages/laraworker/*` to root, delete Laravel app files.

Files to keep/restructure:
- `src/` — PHP source (ServiceProvider, Commands)
- `stubs/` — Worker stubs (worker.ts, shims.ts, tar.ts, build-app.mjs, php.ts.stub, wrangler.jsonc.stub)
- `config/laraworker.php` — Package config
- `composer.json` — Package composer (kieranbrown/laraworker)
- `php-wasm-build/` — Custom WASM build files (.php-wasm-rc, PhpCgiBase.mjs, etc.)
- `tests/` — Package tests

Files to delete: `app/`, `bootstrap/`, `config/` (Laravel), `database/`, `routes/`, `resources/`, `public/`, `storage/`, `.env*`, root `composer.json` (Laravel), `.cloudflare/` (this becomes the generated output)

### Task 1.2: Update composer.json for standalone package
Add require-dev with orchestra/testbench for testing. Ensure autoload PSR-4 maps correctly. Add proper type: library.

### Task 1.3: Create a test Laravel app fixture (for development/testing)
A minimal `tests/fixtures/laravel-app/` or use orchestra/testbench for integration testing the build/deploy commands.

## Phase 2: Command & Build Pipeline Improvements

### Task 2.1: Improve InstallCommand — support both runtime modes
The install command should detect and support:
- **NPM package mode** (default): Uses `php-cgi-wasm` npm package + extension shared libs
- **Custom WASM mode** (advanced): Uses user's pre-built WASM from php-wasm-builder

Key improvements:
- Auto-detect `bun` vs `npm` more robustly
- Add `--runtime=npm|custom` flag
- Generate `wrangler.jsonc` with proper assets config and `run_worker_first` patterns
- Add `.env.production` generation with sensible defaults

### Task 2.2: Improve BuildCommand — production optimizations
The build script (`build-app.mjs`) needs:
- Run `composer install --no-dev` in a temp directory (don't modify user's vendor)
- Run `php artisan config:cache`, `route:cache`, `view:cache` with path fixup
- Strip Carbon locales (keep only `en*`)
- Strip vendor docs/tests/markdown/tooling configs
- Follow symlinks in tar (Composer path repos)
- Disable Composer platform check
- Report final bundle sizes with breakdown

### Task 2.3: Improve worker.ts stub — static asset routing
Current worker manually checks extensions. Better approach:
- Let Cloudflare Static Assets handle static files via `run_worker_first` config
- Only invoke worker for non-asset requests
- Add immutable cache-control headers for `/build/` assets (hashed by Vite)
- Remove redundant static file routing from worker code

Wrangler config should be:
```jsonc
{
  "assets": {
    "directory": "./dist/assets",
    "binding": "ASSETS",
    "run_worker_first": ["!/build/*", "!/favicon.ico", "!*.css", "!*.js", "!*.png", "!*.jpg", "!*.svg", "!*.woff2"]
  }
}
```

Actually, the better approach: `run_worker_first = false` (default) means static assets are served first. Worker only gets invoked for non-matching requests. So the worker stub should just handle PHP routing — no static file checks needed! The worker.ts becomes much simpler.

### Task 2.4: Add cache-control headers for hashed Vite assets
For assets in `/build/assets/` that have content hashes (e.g., `app-CKl8NZMC.js`), we need immutable caching. Two approaches:
- Use a `_headers` file in the assets directory (Cloudflare Pages approach)
- Or use `run_worker_first` for `/build/*` to add headers programmatically

Research needed: whether Cloudflare Workers Static Assets support `_headers` files or if we need `run_worker_first` + ASSETS binding for header manipulation.

## Phase 3: Performance Optimization (The Big Wins)

### Task 3.1: Optimize app.tar.gz — aggressive vendor stripping
Current: 3.5MB compressed. Target: < 2MB compressed.

Strategies:
- Use `composer install --no-dev --no-scripts --optimize-autoloader`
- Strip vendor: `*/tests/`, `*/Tests/`, `*/test/`, `*/.github/`, `*/docs/`, `*/*.md` (except LICENSE), `*/CHANGELOG*`, `*/UPGRADE*`, `*/phpunit.xml*`, `*/.editorconfig`, `*/.gitattributes`, `*/.gitignore`, `*/phpstan*`, `*/psalm*`, `*/.php-cs-fixer*`
- Strip unused Laravel components (if user isn't using them): broadcasting, mail, notifications, etc.
- Consider PHP class preloading / autoloader optimization
- Deduplicate: some vendor packages include duplicate files

### Task 3.2: Investigate OPcache-like precompilation
The biggest bottleneck is "PHP WASM execution (parsing all framework files without OPcache)". Ideas:
- **PHP preloader**: Bundle all used PHP files into a single file (like php-scoper or box)
- **Laravel Octane-style**: Keep PHP process warm between requests (already done via module scope)
- **Autoloader optimization**: `composer dump-autoload --classmap-authoritative` to eliminate PSR-4 filesystem lookups
- **Config/route/view caching**: Already planned, but ensure cached files are in the tar

### Task 3.3: WASM binary size optimization
Current custom build: 9.8MB raw, ~2.6MB gzipped.
Current npm package approach: php-cgi-wasm + shared libs.

For the custom build approach:
- PHP 8.2 with MAIN_MODULE=0, OPTIMIZE=z already applied
- Explore PHP 8.3 (newer Emscripten may produce smaller output)
- Try strip-debug, strip-producers on WASM
- Enable only truly needed extensions: ctype + filter + tokenizer (already done)
- Consider: does `WITH_CTYPE=0` actually remove it? Laravel needs ctype. Check if it's baked into core.

For npm package approach:
- wasm-opt already applied in build script
- Try wasm-opt with `--converge` flag for multiple passes
- Profile: which shared libs are actually loaded? Can we skip libxml2?

### Task 3.4: Filesystem initialization optimization
Current: fetch app.tar.gz → decompress → untar into MEMFS.
Ideas:
- **Lazy filesystem**: Don't untar everything upfront. Create a virtual FS that reads from tar on demand
- **Pre-built MEMFS snapshot**: Cloudflare's Python workers use WASM memory snapshots. Can we do similar?
- **Smaller tar format**: Current tar has 5,392 entries. Can we merge PHP files into fewer, larger chunks?
- **Parallel initialization**: Start PHP WASM instantiation and tar fetch simultaneously (already done via module scope + lazy init)

### Task 3.5: PHP stubs optimization
Current stubs include iconv, mb_split, openssl_* shims. With custom WASM build (no extensions), these are all needed. But:
- The openssl stubs use XOR encryption — not secure. Consider if cookie middleware can use `hash_hmac` instead
- If user enables mbstring extension in npm mode, remove mb_split stub
- If user enables openssl extension, remove openssl stubs
- Make stubs conditional based on config

## Phase 4: Inertia SSR in Workers (Breakthrough Feature)

### Task 4.1: Research & prototype Inertia SSR in same worker
Proven feasible by https://geisi.dev/blog/deploying-inertia-vue-ssr-to-cloudflare-workers/

Architecture:
1. PHP generates Inertia response (JSON with page data)
2. Worker intercepts the response
3. Worker runs Inertia SSR rendering (Vue/React `renderToString`)
4. Worker injects rendered HTML back into response
5. Return fully SSR'd page

Key insight: This doesn't need a separate SSR server. The worker IS the SSR server. The rendering happens inline after PHP responds.

Implementation:
- Add `@inertiajs/server` and `@vue/server-renderer` (or React equivalent) to the worker bundle
- The worker intercepts PHP responses with `X-Inertia` header
- Extracts page data, calls `renderToString`, injects into HTML shell
- Falls back to CSR if SSR fails

### Task 4.2: Build pipeline for Inertia SSR bundle
- Vite builds SSR bundle alongside client bundle
- SSR bundle is imported into worker.ts
- Must tree-shake aggressively — SSR bundle can be large

### Task 4.3: Config option for Inertia SSR
Add to `config/laraworker.php`:
```php
'inertia' => [
    'ssr' => true,
    'framework' => 'vue', // or 'react'
],
```

## Phase 5: Developer Experience

### Task 5.1: Improve dev command with watch mode
`laraworker:dev` should:
- Watch for PHP file changes → rebuild tar
- Watch for JS file changes → Vite rebuilds
- Run `wrangler dev` with hot reload

### Task 5.2: Improve deploy command with pre-flight checks
- Check WASM binary exists
- Check app.tar.gz size (warn if > 3MB for free tier)
- Check wrangler auth
- Show deployment URL after success

### Task 5.3: Add `laraworker:status` command
Show current config, bundle sizes, estimated tier requirements.

## Implementation Order

1. **Phase 1** (Foundation) — Must be done first
2. **Phase 2** (Build improvements) — Depends on Phase 1
3. **Phase 3** (Performance) — Can start in parallel with Phase 2
4. **Phase 4** (Inertia SSR) — Independent, can start after Phase 1
5. **Phase 5** (DX) — Ongoing, can start after Phase 2

## Critical Files

Package source:
- `src/LaraworkerServiceProvider.php`
- `src/Console/InstallCommand.php`
- `src/Console/BuildCommand.php`
- `src/Console/DevCommand.php`
- `src/Console/DeployCommand.php`
- `config/laraworker.php`

Worker stubs:
- `stubs/worker.ts` — Worker entry point
- `stubs/php.ts.stub` — PHP runtime (npm mode)
- `stubs/build-app.mjs` — Build script
- `stubs/shims.ts` — Browser API shims
- `stubs/tar.ts` — Tar unpacker
- `stubs/wrangler.jsonc.stub` — Wrangler config template

Custom WASM:
- `php-wasm-build/.php-wasm-rc` — Build config
- `php-wasm-build/PhpCgiBase.mjs` — Stripped PHP CGI base class