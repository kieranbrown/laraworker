# OPcache SAPI Lifecycle Fix — Persist Opcodes Across Requests

## Problem

Each request calls PHP CGI `main()` which does full `php_module_startup()` → execute → `php_module_shutdown()`. This destroys OPcache SHM every request. OPcache compiles all files, caches them, then throws the cache away — making it actively harmful (~2.5x slower than without OPcache).

## Root Cause

`PhpCgiBase.mjs` line 665 calls `main` for each HTTP request. The PHP CGI `main()` function (in `sapi/cgi/cgi_main.c`) does:
1. `cgi_sapi_module.startup()` → `php_module_startup()` — initializes OPcache, allocates SHM
2. Process the single request
3. `php_module_shutdown()` — destroys OPcache, frees SHM

The `wasm_sapi_cgi_init()` export (added by seanmorris/php-wasm patch) only does `putenv("USE_ZEND_ALLOC=0")` — it does NOT handle module startup.

## Solution: Static If-Guard Approach (Implemented)

Patch `cgi_main.c` with a `static int _wasm_module_started = 0;` if-guard:
- **First call to `main()`**: run full startup (sapi_startup, php_module_startup, etc.), set flag, then handle request
- **Subsequent calls**: skip startup entirely, jump directly to `zend_first_try` (request handling)
- **Guard `php_module_shutdown()` and `sapi_shutdown()`** with `#ifndef __EMSCRIPTEN__` so module persists
- **Guard `php_ini_builder_deinit()` and `php_ini_path_override` free** to prevent UB on subsequent calls

This avoids goto (which can confuse Asyncify's stack transformation) and uses a simple if-guard instead.

### Key Implementation Details

1. The patch uses awk to modify `cgi_main.c` during the Docker build
2. Anchor points: `int skip_getopt = 0;` (after last local decl) and `zend_first_try {` (start of request handling)
3. Local variable initializers (above the if-guard) still run on every call
4. `noExitRuntime: true` is set in PhpCgiBase.mjs phpArgs to prevent Emscripten runtime teardown

### Critical Build Chain Issue Discovered

The playground uses a Composer path repository with symlink to the original repo. When `php artisan laraworker:build` runs, `dirname(__DIR__)` in BuildDirectory.php follows the symlink, copying the WASM binary from the **original repo** instead of the mirror. The fix is to manually copy the patched binary after building.

## Results

| Metric | Before (no patch) | After (with patch) |
|--------|-------------------|-------------------|
| Warm TTFB | ~700ms | **~17ms** |
| OPcache hits | 0 (destroyed per request) | **14,719+ and growing** |
| OPcache hit rate | 0% | **89.2%** |
| start_time | Changes every request | **Constant** |
| Cached scripts | 0 (recompiled each time) | **884** |
| Memory usage | N/A | 16.4 MB / 32 MB |

## Files Modified

| File | Change |
|------|--------|
| `php-wasm-build/patches/cgi-persistent-module.sh` | Patches cgi_main.c for persistent module lifecycle |
| `php-wasm-build/build.sh` | Applies the patch during WASM build |
| `php-wasm-build/php8.5-cgi-worker.mjs.wasm` | Rebuilt binary (output of build.sh) |

## Verification Steps

1. Build WASM: `bash php-wasm-build/build.sh` (~10 min Docker build)
2. Build playground: `php artisan laraworker:build`
3. **Manual step**: Copy patched binary from `php-wasm-build/php8.5-cgi-worker.mjs.wasm` to `playground/.laraworker/php-cgi.wasm` (due to symlink issue)
4. Start dev server: `cd playground/.laraworker && npx wrangler dev`
5. Verify: `curl http://localhost:8788/__opcache-status` (hits should increase across requests)
6. Deploy: `cd playground/.laraworker && npx wrangler deploy`
7. Verify production: `curl https://laraworker.kswb.dev/__opcache-status`

## References

- seanmorris/php-wasm patch/php8.5.patch — existing cgi_main.c modifications
- WordPress Playground OPcache PR #2400 — similar problem, different SAPI
- PHP source: sapi/cgi/cgi_main.c — the main() function we're patching
