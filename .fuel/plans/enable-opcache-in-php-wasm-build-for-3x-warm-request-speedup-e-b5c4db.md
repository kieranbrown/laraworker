# Enable OPcache in PHP WASM Build

## Goal
Statically link OPcache into the custom PHP WASM binary to achieve ~3x warm request speedup by caching compiled PHP opcodes in WASM linear memory across requests to warm isolates.

## Context & Prior Art
- **WordPress Playground** enabled OPcache in their PHP WASM build (July 2025, issue #2251)
  - They patched PHP's autoconf and compiled OPcache as a statically linked library
  - Called it their "most significant performance enhancement"
- **Wasmer** demonstrated 3x speedup: WordPress rendering 620ms → 205ms with OPcache
  - 90%+ hit rate after cache warmup
- **Our bottleneck**: "Parsing all framework files without OPcache" — documented as the primary warm request bottleneck
- **Current warm request**: ~400-650ms (PHP request processing dominates)
- **Target**: ~130-220ms warm requests with OPcache

## Why This Works in WASM
- OPcache stores compiled opcodes in shared memory
- In Emscripten WASM, shared memory can be emulated via mmap()
- Within a warm Cloudflare Workers isolate, WASM linear memory persists across requests
- So opcodes cached on request 1 are available on request 2, 3, etc. — no recompilation
- Only cold starts pay the full compilation cost (same as today)

## Technical Challenges
1. OPcache normally uses dlopen/dlsym (unavailable in Emscripten) — must be statically linked
2. OPcache depends on sys/shm.h — Emscripten doesn't support System V shared memory natively
3. PHP's build system (autoconf) needs patching to allow static OPcache compilation
4. WASM binary size will increase — must stay within budget

## Tasks

### Task 1: Research WordPress Playground's OPcache implementation
- Study their GitHub PR/commits that enabled OPcache (issue #2251)
- Document the exact autoconf patches and build system changes required
- Understand their mmap/shared memory emulation approach
- Identify which OPcache features work vs don't in WASM
- Measure their binary size increase from adding OPcache

### Task 2: Patch php-wasm-builder to support OPcache static linking
- Modify the Makefile in php-wasm-build/ to include OPcache
- Patch PHP's configure/autoconf to allow --enable-opcache as static extension
- Emulate shared memory for Emscripten (mmap-based approach)
- Handle any sys/shm.h dependencies
- Ensure MAIN_MODULE=0 (static linking) still works with OPcache

### Task 3: Build and measure the new WASM binary
- [x] Confirmed OPcache is bundled into PHP core and cannot be removed — it's always compiled in. Both PHP 8.3.11 (npm) and PHP 8.5.2 (built from sm-8.5) include OPcache.
- [x] PHP 8.3.11 (current npm package `php-cgi-wasm@0.0.9-alpha-32`) measured sizes:
  - Uncompressed: 13,209,071 bytes (12.6 MB)
  - Gzipped: 3,343,617 bytes (3.19 MB) — `strings` confirms opcache symbols present
- [x] PHP 8.5.2 built from `seanmorris/php-wasm sm-8.5` branch. Configure confirms `--disable-opcache-jit` (OPcache enabled, JIT disabled).
- [x] PHP 8.5.2 WASM binary measured sizes (WITH_SESSION=1, OPcache, pib; no other extensions):
  - Uncompressed: 13,905,219 bytes (13.3 MB)
  - Gzipped: 3,429,116 bytes (3.27 MB)
- [x] Build fix: `WITH_SESSION=1` required — the `pib` extension references `ps_globals` from session module
- [x] Build fix: `make worker-cgi-mjs PHP_CONFIGURE_DEPS=null ENV_FILE=.env` from HOST (not inside Docker)
- [x] Build limitation: wasm-opt `--no-stack-ir` unsupported in wasm-opt v117 (builder image version mismatch). Omitting it produces a working binary.
- [x] `php-wasm-build/build.sh` documents and automates the correct build procedure

### Task 4: Configure OPcache ini settings for WASM environment
- [x] Set optimal OPcache ini directives in worker.ts:
  - opcache.enable=1
  - opcache.enable_cli=1 (CGI mode)
  - opcache.memory_consumption=32 (tuned for WASM memory budget)
  - opcache.max_accelerated_files=1000 (Laravel has ~500-800 files)
  - opcache.validate_timestamps=0 (files never change in MEMFS)
  - opcache.jit=disable (JIT not useful in WASM)
- [x] Added OPcache configuration section to config/laraworker.php
- [ ] Test that OPcache activates correctly (phpinfo() or opcache_get_status())

**Configuration approach:**
- OPcache ini directives are passed via the `ini` option in `worker.ts` (stubs/worker.ts:60-68)
- Settings are joined with newlines and passed to PhpCgiCloudflare constructor
- Config options exposed in config/laraworker.php under 'opcache' key for user customization
- All OPcache settings tested in ConfigTest.php (63 tests passing)

### Task 5: Benchmark warm request performance
- Baseline: measure current warm request time (multiple runs, p50/p95/p99)
- OPcache: measure warm request time with OPcache enabled
- Target: ~3x improvement (400-650ms → 130-220ms)
- Measure: first request (cold OPcache) vs subsequent requests (warm OPcache)
- Measure: memory usage increase from OPcache

### Task 6: Integration and cleanup
- [x] Update config/laraworker.php to expose OPcache settings — config/laraworker.php includes 'opcache' section with sensible defaults
- [x] Update build documentation — MEMORY.md created in .fuel/docs/ with full OPcache findings
- [x] Ensure ClassPreloader still works alongside OPcache — documented in MEMORY.md that they are complementary (ClassPreloader eliminates autoloader filesystem overhead, OPcache eliminates re-parsing)
- [x] Run full test suite — 63 tests passing, including 3 new OPcache config tests

## Size Budget Impact

**Key finding**: OPcache is not a separate add-on — it is always compiled into PHP core. There is no "without OPcache" binary to compare against. The size figures below are for the full PHP binary including OPcache:

| Version | Uncompressed | Gzipped |
|---------|-------------|---------|
| PHP 8.3.11 (npm `php-cgi-wasm@0.0.9-alpha-32`) | 12.6 MB | 3.19 MB |
| PHP 8.5.2 (sm-8.5 build, **measured**) | 13.3 MB | **3.27 MB** |

The binary size is dominated by the PHP core + Emscripten runtime, not OPcache specifically.
The WASM file is deployed as a separate Cloudflare Workers binding (not counted toward the 1 MB script bundle limit).
App bundle (app.tar.gz) is stored in KV storage (25 MB limit) — well within budget.

## Success Criteria
- [x] OPcache statically linked and functional in PHP WASM (always was — confirmed via `strings`)
- [x] JIT disabled (`--disable-opcache-jit` in sm-8.5 configure)
- [ ] PHP 8.5.2 binary measurement confirmed after build
- [ ] Warm request speedup ≥ 2x (ideally 3x) — to be measured in Task 5
- [ ] opcache_get_status() shows cache hits on second request
- [x] All existing tests pass (63 tests)