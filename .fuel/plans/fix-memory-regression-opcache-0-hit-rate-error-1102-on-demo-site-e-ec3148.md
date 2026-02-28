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

### Task 1: Diagnose why PHP returns non-zero exit code on demo site
- **Complexity**: moderate
- Deploy a debug build or add temporary logging to capture the actual exit code and stderr from PhpCgiBase
- Check if the rebuilt WASM binary (bbe63d92) has the persistent module patch working correctly
- Verify `_wasm_module_started` variable behavior in the compiled WASM
- Check if `refresh()` is being called on every request by adding a console.warn

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

### Task 5: Add WASM memory profiling tooling
- **Complexity**: moderate
- Create a diagnostic mode that reports:
  - WASM linear memory usage (current heap size vs initial)
  - OPcache SHM utilization (used vs allocated)
  - MEMFS usage (tar extracted size)
  - JS heap estimate (if available via performance.measureUserAgentSpecificMemory or similar)
  - Number of refresh() calls (module rebuilds)
- Surface this via response headers when MEMORY_DEBUG=true
- Add a `/__memory-report` diagnostic endpoint

### Task 6: Guard against unnecessary refresh() calls
- **Complexity**: moderate
- In the worker stub's catch block, distinguish between:
  - WASM RuntimeError (actual crash → refresh is needed)
  - PHP returning non-zero exit code due to application error (refresh may not be needed)
- Consider patching PhpCgiBase.mjs or overriding in PhpCgiCloudflare to not call refresh() on non-zero exit codes unless it's a RuntimeError
- This prevents OPcache destruction on recoverable PHP errors

## Priority Order

1. Task 2 (reduce OPcache memory) — quick win, may fix Error 1102
2. Task 1 (diagnose exit code) — root cause of 0% hit rate
3. Task 6 (guard refresh) — prevents OPcache reset on non-crash errors
4. Task 3 (fix comment) — trivial
5. Task 4 (smoke test) — prevents future regressions
6. Task 5 (memory profiling) — long-term observability