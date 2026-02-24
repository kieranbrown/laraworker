# OPcache SAPI Lifecycle Fix — Persist Opcodes Across Requests

## Problem

Each request calls PHP CGI `main()` which does full `php_module_startup()` → execute → `php_module_shutdown()`. This destroys OPcache SHM every request. OPcache compiles all files, caches them, then throws the cache away — making it actively harmful (~2.5x slower than without OPcache).

## Root Cause

`PhpCgiBase.mjs` line 665 calls `main` for each HTTP request. The PHP CGI `main()` function (in `sapi/cgi/cgi_main.c`) does:
1. `cgi_sapi_module.startup()` → `php_module_startup()` — initializes OPcache, allocates SHM
2. Process the single request
3. `php_module_shutdown()` — destroys OPcache, frees SHM

The `wasm_sapi_cgi_init()` export (added by seanmorris/php-wasm patch) only does `putenv("USE_ZEND_ALLOC=0")` — it does NOT handle module startup.

## Solution

Patch `cgi_main.c` to separate module lifecycle from request lifecycle:
- `php_module_startup()` runs ONCE (first request or explicit startup call)
- Each request only runs `php_request_startup()` → execute → `php_request_shutdown()`
- `php_module_shutdown()` NEVER runs between requests (only on isolate teardown / refresh)

This is the same fundamental approach WordPress Playground uses (though they use the embed SAPI).

## Technical Approach

### Patch cgi_main.c (minimal diff)

Add a `static bool _wasm_module_started = false;` guard:
- First call to `main()`: run full startup, set flag, then handle request
- Subsequent calls: skip startup, jump to request handling
- Remove `php_module_shutdown()` call (or guard it behind `!_wasm_module_started`)

Alternatively, export separate functions:
- `wasm_sapi_cgi_startup()` — module startup (called once from `refresh()`)
- `wasm_sapi_cgi_handle_request()` — request-only cycle (called per HTTP request)
- `wasm_sapi_cgi_shutdown()` — module shutdown (called on error/refresh only)

The separate-functions approach is cleaner but requires updating PhpCgiBase.mjs.

### Update PhpCgiBase.mjs — ✅ NO CHANGES NEEDED

**Verified by f-461724**: Approach A (static guard) was used, so `main()` still works as the only entry point. PhpCgiBase.mjs calls `php.ccall('main', ...)` at line 665 and this is transparent to the caller — `main()` internally skips startup on repeat calls via `goto wasm_handle_request`.

Key verifications:
- `request()` (line 665): `main()` call unchanged — static guard handles lifecycle internally
- `refresh()` (line 360): Creates new WASM instance → `_wasm_module_started` resets to 0 → first `main()` does full startup. Correct.
- Error recovery (lines 735, 750): `refresh()` destroys old instance. OPcache cache lost but safe recovery.
- `PhpCgiCloudflare` (stubs/php.ts.stub): Only overrides constructor. Inherits `request()` from PhpCgiBase. No changes needed.
- `worker.ts.stub`: Calls `instance.request(request)` — no changes needed.

**If Approach B (separate exports) were used later**, PhpCgiCloudflare subclass (stubs/php.ts.stub) would be the cleanest place to override `request()` — avoids modifying the upstream PhpCgiBase.mjs file.

### Verify OPcache Persistence

Deploy and use `/__opcache-status` endpoint:
- Request 1: misses > 0, hits = 0
- Request 2+: hits increasing — OPcache working!

## Expected Results

- 1st request to warm isolate: ~400ms (OPcache cold miss, compiles all files)
- 2nd+ requests: <100ms (OPcache hot, cached opcodes — no recompilation)
- The <100ms target matches what was briefly observed during "No input file" error responses

## Files Modified

| File | Change |
|------|--------|
| `php-wasm-build/patches/cgi-persistent-module.sh` | NEW — patches cgi_main.c for persistent module |
| `php-wasm-build/build.sh` | Add new patch to build pipeline |
| `php-wasm-build/PhpCgiBase.mjs` | ✅ No changes needed — Approach A is transparent to caller |
| `php-wasm-build/php8.5-cgi-worker.mjs.wasm` | Rebuilt binary (output of build.sh) |
| `stubs/worker.ts.stub` | ✅ No changes needed — API unchanged |

## Agent Workflow

1. Create the patch script and update build.sh
2. Rebuild WASM: `cd php-wasm-build && ./build.sh` (~20-60 min)
3. Test locally: `scripts/playground-build.sh && cd playground/.laraworker && npx wrangler dev`
4. Verify: `curl http://localhost:8787/__opcache-status` (twice — check hits increase)
5. Deploy: `cd playground/.laraworker && npx wrangler deploy`
6. Verify production: `curl https://laraworker.kswb.dev/__opcache-status` + timing tests

## References

- seanmorris/php-wasm patch/php8.5.patch — existing cgi_main.c modifications
- WordPress Playground OPcache PR #2400 — similar problem, different SAPI
- PHP source: sapi/cgi/cgi_main.c — the main() function we're patching
- AGENTS.md — full workflow including WASM rebuild instructions