# Fix Memory Regression: OPcache 0% + Error 1102

## Context

The demo site (laraworker.kswb.dev) shows:
- OPcache hit rate always 0% (never caches across requests)
- Error 1102 (Worker exceeded resource limits) after a few refreshes

**Regression window**: commits since ~1.5.0 (3806ae5b), specifically:
- `4f9d11f7` — Added crash detection + retry logic (500 → check FS → re-init)
- `bbe63d92` — Rebuilt WASM binary + updated upstream helper modules
- `286e5b18` — Disabled ALLOW_TABLE_GROWTH, changed WORKER_MAX_REQUESTS default to 0

## Root Cause Analysis

### Why OPcache is 0%

`PhpCgiBase.mjs` calls `this.refresh()` when PHP exits with non-zero code (line 701, `finally` block). `refresh()` creates a brand new Emscripten module, destroying WASM linear memory and OPcache SHM.

If PHP crashes or returns non-zero exit code on every request, OPcache resets every time → 0% hit rate.

Possible causes for consistent non-zero exit:
1. The WASM binary rebuilt in `bbe63d92` may have a subtle issue (e.g. persistent module patch behavior changed, or upstream helper module incompatibility)
2. A runtime PHP error that causes a fatal (crash) before response completes
3. The crash retry logic in worker.ts.stub also calls `initializeFilesystem()` on 500 + missing FS, which doesn't destroy the module but re-extracts the tar

### Why Error 1102

128 MB total budget (WASM linear memory + JS heap):
- 64 MB initial WASM linear memory (can grow via ALLOW_MEMORY_GROWTH=1, hardcoded in Makefile)
- OPcache: 24 MB memory_consumption + 4 MB interned_strings = 28 MB (within WASM linear memory)
- Tar extraction: ~16 MB in MEMFS
- PHP heap, stack, compiled code sections
- JS heap overhead (strings, buffers, SSR)

If the module is being rebuilt on every request (due to crashes), each cold start involves tar extraction + PHP compilation, which is the peak memory moment and can push past 128 MB.

### Stale comment in worker.ts.stub

The WORKER_MAX_REQUESTS comment says "With ALLOW_TABLE_GROWTH=1 in the WASM binary" but 286e5b18 set it to 0. Minor, but should be fixed.

## Tasks

### Task 1: Diagnose why PHP returns non-zero exit code on demo site ✅
- **Complexity**: moderate
- **Status**: Done (f-23f67f)
- **Files modified**:
  - `php-wasm-build/PhpCgiBase.mjs` — Added `lastExitCode` property, `this.lastExitCode = exitCode` in finally block, RuntimeError vs JS exception distinction in catch block
  - `stubs/php.ts.stub` — Added `refresh()` override with logging + `_refreshCount` tracker + `refreshCount` getter
  - `stubs/worker.ts.stub` — Added pre/post-request tracking (countBefore/After, refreshesBefore/After), `X-Php-Exit-Code` and `X-Module-Refreshed` response headers, stderr capture on refresh
- **Diagnostic signals available after deploy**:
  - `X-Php-Exit-Code` header: 0 = success, >0 = PHP fatal, -1 = JS exception before main() returned
  - `X-Module-Refreshed: true` header: present when refresh() was called during request
  - `X-Request-Count` header: should increment; resets to 1 if module was rebuilt
  - Console: `[php-wasm] refresh() #N — module rebuild` with exit code and stderr
  - Console: `[php-wasm] WASM RuntimeError` vs `[php-wasm] JS exception` distinguishes crash types
  - Console: `[php-wasm] Module refreshed during request to /path` with full diagnostics
- **Key findings**:
  - `_wasm_module_started` is a C `static int` in WASM linear memory (not JS-visible), but build.sh applies the patch and the build verification confirms it's present
  - `shouldRunNow = false` in Emscripten wrapper means `callMain()` is NOT called during module init — `main()` is only invoked via `ccall("main")` from PhpCgiBase
  - **Double-refresh bug**: On the exception path (catch block), `refresh()` is called at line 696, then `finally` block also calls `refresh()` at line 704 because `exitCode` stays -1. Two rebuilds instead of one.
  - The exit code semantics: `0` = PHP handled request successfully (even 500 HTTP status from app error), `non-zero` = PHP fatal/startup failure, `-1` = exception thrown before `main()` returned
- **Gotchas**:
  - `lastExitCode` is set in the finally block, so it captures the exit code even when catch runs first
  - `this.error` (stderr buffer) is NOT cleared by `refresh()` — it persists until next request's `this.error = []` at line 589
  - `PhpCgiCloudflare.refresh()` is called from both PhpCgiBase's catch and finally blocks — the override logs both calls
- **Next steps** (for Task 6):
  - In the catch block, only call `refresh()` for `WebAssembly.RuntimeError` (actual WASM corruption)
  - In the finally block, consider NOT calling `refresh()` on non-zero exit code — PHP fatals don't necessarily corrupt the module
  - Fix the double-refresh by either removing the catch-block `refresh()` call or setting a flag to skip the finally-block one

### Task 2: Reduce OPcache memory to fit within budget
- **Complexity**: simple
- Change `memory_consumption` from 24 to 16 in `config/laraworker.php`
- This was confirmed working previously (MEMORY.md says "16 MB memory_consumption works fine on warm isolates")
- Consider reducing `interned_strings_buffer` from 4 to 2 as well
- Update MEMORY.md if values change

### Task 3: Fix stale ALLOW_TABLE_GROWTH comment in worker.ts.stub
- **Complexity**: trivial
- Line 63: "With ALLOW_TABLE_GROWTH=1 in the WASM binary" should say "With the persistent module patch" or similar, since ALLOW_TABLE_GROWTH is now 0

### Task 4: Add smoke test script for deployed workers ✅
- **Complexity**: moderate
- **Status**: Done (f-c9e99b)
- **File**: `scripts/smoke-test-deployed.sh`
- **Usage**: `./scripts/smoke-test-deployed.sh <URL> [--requests=N] [--opcache-debug]`
- Checks:
  1. All HTTP responses are 200 (detects Error 1102 in body)
  2. X-Request-Count header increments (not stuck at 1 = module cycling)
  3. OPcache hit rate >0% via `/__opcache-status` (with `--opcache-debug` flag)
- **Key decisions**:
  - OPcache check is opt-in via `--opcache-debug` since `/__opcache-status` requires `OPCACHE_DEBUG=true` on the worker
  - X-Request-Count check allows for CF multi-isolate routing (different isolates have different counts) but fails if ALL responses show count=1
  - Uses `curl -D` for header capture instead of `-w` for reliability

### Task 5: Add WASM memory profiling tooling ✅
- **Complexity**: moderate
- **Status**: Done (f-4be28b)
- **File**: `stubs/worker.ts.stub`
- **Response headers** (when `MEMORY_DEBUG=true`):
  - `X-Wasm-Heap-Used`: WASM linear memory bytes (via `HEAP8.buffer.byteLength`)
  - `X-OPcache-Memory-Used` / `X-OPcache-Memory-Total`: from `opcache_get_status(false)`
  - `X-Memfs-Size`: total bytes under `/app` in MEMFS (recursive walk)
  - `X-Module-Refreshes`: count of `initializeFilesystem()` calls (proxy for refresh count)
- **Endpoint**: `/__memory-report` returns full JSON breakdown (wasmHeap, memfs, opcache, phpMemory, requestCount, refreshCount)
- **Key decisions**:
  - OPcache stats fetched via lightweight `__lw-diag.php` written to MEMFS during `initializeFilesystem()` — avoids modifying the app tarball or php-stubs.php
  - `refreshCount` tracks `initializeFilesystem()` calls rather than PhpCgiBase.refresh() directly, since the worker can only observe FS wipes (which require re-init)
  - MEMFS size walks `/app` only (not entire FS) to avoid Emscripten internal nodes
  - OPcache header fetch is an internal PHP request per main request — acceptable overhead since MEMORY_DEBUG is a debug-only feature
- **Gotchas**:
  - `__lw-diag.php` must be re-created after every FS re-init (it's in MEMFS which gets wiped on refresh)
  - The internal PHP request for OPcache stats runs AFTER the main request completes; if PHP crashed and FS was re-inited, the file is re-created by `initializeFilesystem()`
  - `getMemfsSize()` uses stat.mode bitmask `(mode & 61440) === 16384` for directory detection (Emscripten FS.isDir may not be exposed)

### Task 6: Guard against unnecessary refresh() calls ✅
- **Complexity**: moderate
- **Status**: Done (f-6c4613)
- **Files**: `stubs/php.ts.stub`, `stubs/worker.ts.stub`
- **Approach**: Override `refresh()` in PhpCgiCloudflare as no-op counting calls.
  WASM crash = count >= 2 (catch + finally). PHP app error = count === 1 (finally only).
- **API**: `needsRestart` getter, `refreshCount` getter, `request()` resets per-request counter.
- **Worker**: checks `instance.needsRestart`, discards instance on crash, preserves OPcache on PHP errors.

## Priority Order

1. Task 2 (reduce OPcache memory) — quick win, may fix Error 1102
2. Task 1 (diagnose exit code) — root cause of 0% hit rate
3. Task 6 (guard refresh) — prevents OPcache reset on non-crash errors
4. Task 3 (fix comment) — trivial
5. Task 4 (smoke test) — prevents future regressions
6. Task 5 (memory profiling) — long-term observability
