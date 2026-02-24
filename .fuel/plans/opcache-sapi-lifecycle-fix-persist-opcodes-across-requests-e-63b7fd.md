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

### [DONE] Patch cgi_main.c (Approach A: static guard with goto)

Implemented via `php-wasm-build/patches/cgi-persistent-module.sh`. Uses a single-pass awk script that applies 6 modifications:

1. **Static guard after variable declarations** (after `int skip_getopt = 0;`):
   - `static int _wasm_module_started = 0;`
   - Re-initializes 10 critical locals (exit_status, fastcgi, cgi, behavior, etc.) since goto skips initializers
   - `goto wasm_handle_request;`

2. **Jump label before `zend_first_try`**:
   - Sets `_wasm_module_started = 1;`
   - Label MUST be outside `zend_first_try` (which expands to `setjmp`) — jumping into it would skip exception handler setup

3. **Guard all 5 `php_module_shutdown()` calls** with `#ifndef __EMSCRIPTEN__`:
   - 2 in getopt handlers (-i phpinfo, -v version)
   - 1 in php_request_startup() failure
   - 1 in php_fopen_primary_script() failure
   - 1 at end of main()

4. **Guard all 2 `sapi_shutdown()` calls** — same pattern

5. **Guard `php_ini_builder_deinit(&ini_builder)`** — ini_builder is an uninitialized local on repeat calls (goto skips its init). Calling deinit on garbage is UB.

6. **Guard `php_ini_path_override` free** — set once during -c getopt, freeing on every request would double-free.

### Key design decisions:
- **Used `int` not `bool`** for `_wasm_module_started` to avoid `#include <stdbool.h>` dependency
- **`tsrm_shutdown()` NOT separately guarded** — it's already inside `#ifdef ZTS` which is never defined for WASM (single-threaded)
- **`fcgi_shutdown()` NOT guarded** — in non-FastCGI mode it's a no-op (sets already-0 flag)
- **PhpCgiBase.mjs NOT modified** — the JS side still calls `main()` per request. The C-side static guard handles the lifecycle. This means no JS API changes needed.

### Gotchas for future work:
- The goto MUST be after all variable declarations (C allows goto past initializers, variables have storage but indeterminate values)
- ALL locals used in the request path must be re-initialized in the guard block
- Error paths (request_startup failure, fopen failure) that call `return FAILURE;` still work — JS sees exitCode != 0 and can handle it
- The `zend_first_try` setjmp runs on every call — EG(bailout) is properly set up for exception handling

### Verify OPcache Persistence

Deploy and use `/__opcache-status` endpoint:
- Request 1: misses > 0, hits = 0
- Request 2+: hits increasing — OPcache working!

## Expected Results

- 1st request to warm isolate: ~400ms (OPcache cold miss, compiles all files)
- 2nd+ requests: <100ms (OPcache hot, cached opcodes — no recompilation)
- The <100ms target matches what was briefly observed during "No input file" error responses

## Files Modified

| File | Change | Status |
|------|--------|--------|
| `php-wasm-build/patches/cgi-persistent-module.sh` | NEW — awk-based patch for persistent module | DONE |
| `php-wasm-build/build.sh` | Add new patch to build pipeline (after opcache-wasm-support) | DONE |
| `php-wasm-build/PhpCgiBase.mjs` | NOT NEEDED — JS still calls main(), C handles lifecycle | N/A |
| `php-wasm-build/php8.5-cgi-worker.mjs.wasm` | Rebuilt binary (output of build.sh) | PENDING REBUILD |
| `stubs/worker.ts.stub` | NOT NEEDED — no JS API changes | N/A |

## Agent Workflow

1. ~~Create the patch script and update build.sh~~ DONE (f-94ba81)
2. Rebuild WASM: `cd php-wasm-build && ./build.sh` (~20-60 min) — PENDING
3. Test locally: `scripts/playground-build.sh && cd playground/.laraworker && npx wrangler dev`
4. Verify: `curl http://localhost:8787/__opcache-status` (twice — check hits increase)
5. Deploy: `cd playground/.laraworker && npx wrangler deploy`
6. Verify production: `curl https://laraworker.kswb.dev/__opcache-status` + timing tests

## References

- seanmorris/php-wasm patch/php8.5.patch — existing cgi_main.c modifications
- WordPress Playground OPcache PR #2400 — similar problem, different SAPI
- PHP source: sapi/cgi/cgi_main.c — the main() function we're patching
- AGENTS.md — full workflow including WASM rebuild instructions