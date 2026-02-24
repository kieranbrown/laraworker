# Static Assets & OPcache Performance Fixes

## Problem Statement

Two performance issues identified on the deployed demo (laraworker.kswb.dev):

### 1. Static files invoke the PHP WASM worker
Files like `/robots.txt` and `/favicon.ico` are NOT deployed as Cloudflare Static Assets. The build only copies `public/build/` (Vite output) to `dist/assets/`. All other public files hit the worker → boot PHP WASM → ~0.5-2.8s response for a static file that should be <50ms.

### 2. OPcache config mismatch & no diagnostics
OPcache IS compiled into the WASM binary and enabled, but:
- `stubs/worker.ts` hardcodes 8MB memory; `config/laraworker.php` says 32MB
- Config values are never templated into the worker stub
- No runtime diagnostic to verify OPcache is actually working
- Performance doc is outdated (says OPcache unavailable)

## Tasks

| Task | ID | Complexity | Description |
|------|----|-----------|-------------|
| Copy public/ static files to Static Assets | f-a69f1f | moderate | Build copies public/ files (except index.php, build/) to dist/assets/ |
| Template OPcache INI into worker.ts | f-7e9db7 | moderate | Replace hardcoded INI with config-driven values from laraworker.php |
| OPcache diagnostic endpoint | f-3530a8 | simple | Add /__opcache-status endpoint (behind env var) for production debugging |
| Review | f-0859b3 | complex | Verify all fixes, test deployment, confirm performance improvement |

## Completed: f-a69f1f — Copy public/ static files to Static Assets

**Files modified:**
- `config/laraworker.php` — Added `public_assets` config option (default `true`)
- `src/BuildDirectory.php` — Added `public_assets` to `writeBuildConfig()` output
- `stubs/build-app.mjs` — Added `copyPublicFiles()` function + invocation after Vite copy block

**How it works:**
- `copyPublicFiles()` recursively scans `ROOT/public/`, skipping `index.php` and `build/`
- Copies remaining files to `DIST_DIR` preserving relative paths (e.g. `public/robots.txt` → `dist/assets/robots.txt`)
- Controlled by `config.public_assets` from build-config.json (defaults to `true`)
- Logs each copied file during build

**Patterns to follow:**
- Config flows: `config/laraworker.php` → `BuildDirectory::writeBuildConfig()` → `build-config.json` → `build-app.mjs`
- Static assets land in `DIST_DIR` which maps to `assets.directory` in wrangler.jsonc

## Completed: f-7e9db7 — Template OPcache INI into worker.ts

**Files modified:**
- `stubs/worker.ts` → renamed to `stubs/worker.ts.stub` — hardcoded OPcache INI lines replaced with `{{OPCACHE_INI}}` placeholder
- `src/BuildDirectory.php` — removed `worker.ts` from `STUBS` constant; added `generateWorkerTs()` method that reads `config('laraworker.opcache')` and templates INI directives into the stub
- `src/Console/BuildCommand.php` — added `generateWorkerTs()` call in the build pipeline (after `copyStubs()`, before `generatePhpTs()`)
- `config/laraworker.php` — changed `memory_consumption` from 32 to 16 (fits WASM memory budget); added `interned_strings_buffer` (4 MB)
- `.fuel/docs/performance-investigation.md` — updated to reflect OPcache IS available and statically compiled; moved from "Tier 3 future" to "Already Implemented"
- `tests/Unit/BuildDirectoryTest.php` — updated `copyStubs` tests (worker.ts no longer in STUBS); added 3 tests for `generateWorkerTs()`

**How it works:**
- `generateWorkerTs()` reads opcache config from `config/laraworker.php`, builds INI directive strings, and replaces `{{OPCACHE_INI}}` in the stub template
- When `opcache.enabled` is false, no INI lines are emitted (empty replacement)
- JIT is explicitly disabled with `opcache.jit=0` and `opcache.jit_buffer_size=0` (not functional in WASM)

**Key decisions:**
- 16 MB memory_consumption: 64 MB WASM linear memory - ~23 MB MEMFS - PHP runtime overhead = ~40 MB available. 16 MB is conservative and safe.
- Worker.ts uses `.stub` extension (consistent with `php.ts.stub` and `wrangler.jsonc.stub` patterns)
- Placeholder uses `{{OPCACHE_INI}}` format (consistent with existing `{{APP_NAME}}`, `{{COMPATIBILITY_DATE}}`)

**Gotchas:**
- The INI lines must be formatted as TypeScript string array elements with correct indentation (8 spaces + quotes + comma)
- `interned_strings_buffer` was missing from config but present in the old hardcoded worker.ts — now properly configurable

## Completed: f-3530a8 — OPcache diagnostic endpoint

**Files modified:**
- `stubs/worker.ts.stub` — Added `/__opcache-status` route (before PHP catch-all) and `OPCACHE_DEBUG` to `Env` interface
- `stubs/build-app.mjs` — Added generation of `__opcache-status.php` diagnostic file

**How it works:**
- New endpoint `/__opcache-status` is checked before the main PHP catch-all routing
- Protected by `env.OPCACHE_DEBUG === 'true'` check (returns 404 if not enabled)
- When enabled, routes request to `/__opcache-status.php` which executes `opcache_get_status()` and returns JSON
- Key metrics exposed: `opcache_enabled`, `cache_full`, `num_cached_scripts`, `num_cached_keys`, `hits`, `misses`, `hit_rate`, `memory_usage`

**Usage:**
1. Set `OPCACHE_DEBUG=true` in wrangler.jsonc vars or as an environment variable
2. Deploy/restart the worker
3. Access `https://your-worker.com/__opcache-status`
4. Check `num_cached_scripts` - should increase as files are cached

**Key decisions:**
- Opt-in only via env var (security - don't expose internals publicly)
- PHP file is auto-generated at build time (no manual maintenance)
- JSON output is pretty-printed for human readability

## Expected Outcome
- Static files served in <50ms from CF edge
- OPcache config matches what's in config/laraworker.php
- Ability to verify OPcache is working in production
- Updated performance documentation

## Review Findings (f-0859b3)

### All Tasks Verified Complete

| Task | Status | Verification |
|------|--------|-------------|
| f-a69f1f: Copy public/ static files | Done | `build-app.mjs:1180-1198` copies public/ (excluding index.php, build/) to dist/assets/. Build output confirms robots.txt, favicon.ico, .htaccess copied. `config/laraworker.php` has `public_assets => true` default. |
| f-7e9db7: Template OPcache INI | Done | `BuildDirectory::generateWorkerTs()` reads `config('laraworker.opcache')` and replaces `{{OPCACHE_INI}}` placeholder in `stubs/worker.ts.stub`. Generated worker.ts confirmed to have config-driven values (not hardcoded). Tests in `BuildDirectoryTest.php` verify templating. |
| f-3530a8: OPcache diagnostic endpoint | Done | Worker intercepts `/__opcache-status` when `OPCACHE_DEBUG=true` env var is set. PHP handler generated at build time via `build-app.mjs:1083-1099`. Returns `opcache_get_status(false)` as JSON. |

### Pre-existing Test Fix
- `DeployCommandTest::deploy calls build first` had a wrong assumption (expected build-app.mjs to be missing, but BuildCommand copies stubs first). Fixed to use `--dry-run` and verify build output instead.

### Quality Gates
- All 66 Pest tests pass
- Build completes successfully (`php artisan laraworker:build`)
- Pint formatting clean
- No debug calls (dd, dump, var_dump) in source
