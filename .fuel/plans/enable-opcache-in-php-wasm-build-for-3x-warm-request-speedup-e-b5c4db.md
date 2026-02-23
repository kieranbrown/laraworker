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

### Task 1: Research WordPress Playground's OPcache implementation ✅

**Key reference**: [PR #2400](https://github.com/WordPress/wordpress-playground/pull/2400) (merged July 22 2025, commit `e0b8815`), addressing issue #2251. PHP 8.5 support in [PR #2950](https://github.com/WordPress/wordpress-playground/pull/2950) (Dec 2025).

**Critical PHP 8.5 finding**: In PHP 8.5, OPcache is **mandatory** — compiled directly into PHP core. It can no longer be disabled or installed as a separate extension. This means `docker-php-ext-install opcache` fails on PHP 8.5 (OPcache already built-in). **The complex patching WP Playground needed for PHP 7/8.0–8.4 is not required for PHP 8.5.**

#### Build System Changes (PHP 7.0–8.4 only — not needed for 8.5)

WP Playground's Dockerfile (`packages/php-wasm/compile/php/Dockerfile`) adds `ARG WITH_OPCACHE` and applies these patches to `ext/opcache/config.m4`:

1. **Force static compilation** (OPcache normally requires dynamic loading):
   ```bash
   sed -i 's/shared,,/no,,/g' ext/opcache/config.m4
   sed -i 's/ext_shared=yes/ext_shared=no/g' ext/opcache/config.m4
   ```

2. **Force anonymous shared memory recognition** (Emscripten supports `mmap(MAP_ANON)` but autoconf can't detect it):
   - PHP 8.4+: `sed -i 's/php_cv_shm_mmap_anon=no/php_cv_shm_mmap_anon=yes/' ext/opcache/config.m4`
   - PHP <8.4: `sed -i 's/have_shm_mmap_anon=no/have_shm_mmap_anon=yes/' ext/opcache/config.m4`

3. **Add glue module** (PHP <8.5): Copy `opcache_module.c` into `ext/opcache/` and add to the source file list:
   ```bash
   sed -i 's/shared_alloc_mmap.c/shared_alloc_mmap.c opcache_module.c/' ext/opcache/config.m4
   ```
   This 44-line C file (`packages/php-wasm/compile/opcache/opcache_module.c`) registers the Zend extension via `PHP_MINIT_FUNCTION(opcache)` → `zend_register_extension(&zend_extension_entry, NULL)`. Without this, OPcache's Zend extension entry point isn't registered during PHP startup in a static build.

4. **PHP configure flags**:
   ```
   --enable-opcache --disable-opcache-jit --disable-huge-code-pages
   ```

#### Shared Memory / mmap Emulation

OPcache's shared memory is emulated via anonymous mmap in WASM linear memory:
- `mmap(NULL, size, PROT_READ|PROT_WRITE, MAP_SHARED|MAP_ANON, -1, 0)`
- Emscripten supports `MAP_ANON` — this is why forcing `php_cv_shm_mmap_anon=yes` works
- `SHM_IPC` (SysV shmget) is NOT available; `SHM_MMAP_ANON` is the working mechanism
- Within a warm WASM isolate, WASM linear memory persists between requests → opcodes cached across requests

#### OPcache Features — WASM Compatibility Matrix

| Feature | Works in WASM? | Notes |
|---------|---------------|-------|
| Opcode caching (SHM_MMAP_ANON) | ✅ Yes | Core feature, confirmed working |
| JIT compilation | ❌ No | Disabled (`--disable-opcache-jit`). WASM is already compiled; JIT would target WASM instructions, not native CPU |
| Huge code pages | ❌ No | Disabled (`--disable-huge-code-pages`). No OS huge page support in WASM |
| File cache (`opcache.file_cache`) | ⚠️ Partial | Works via MEMFS but files don't persist across isolate evictions |
| `opcache.preload` | ❌ Not tested | May work but untested in WP Playground context |
| `opcache_get_status()` | ⚠️ Bug | `opcache_enabled` field returns `false` (PHP bug #75070) despite cache functioning |
| Timestamp validation | ✅ Disable it | Set `opcache.validate_timestamps=0` — MEMFS files never change |

#### Performance Results (WordPress Playground)

- Hit rate: **~90%+** after cache warmup (75% with file-cache-only mode)
- Wasmer (WASIX, not Emscripten): WordPress 620ms → 205ms (**3x speedup**)
- WP Playground (Emscripten): "most significant performance enhancement" — no exact ms figure published but cited as 42% reduction (185ms → 108ms in their environment)
- `build.js` default: `WITH_OPCACHE: 'yes'` — enabled by default since July 2025

#### Binary Size Impact (WP Playground)

WP Playground exceeded npm's 100 MB package size limit with PHP 7.4–8.5 all in one package, forcing per-version package splits (`@php-wasm/web-8.5`, `@php-wasm/node-8.4`, etc.). However this was due to carrying multiple PHP versions, not OPcache specifically. OPcache's C code contribution to binary size is modest — subsumed into the PHP core.

#### Why Our Build Differs (PHP 8.5 + seanmorris/php-wasm sm-8.5)

The `seanmorris/php-wasm sm-8.5` branch targets PHP 8.5.2. Since PHP 8.5 makes OPcache mandatory:
- No `config.m4` patching required
- No `opcache_module.c` glue required
- No `ext_shared` override required
- `--disable-opcache-jit` is still needed (and sm-8.5 branch applies it)
- OPcache is confirmed present via `strings php8.5-cgi-worker.wasm | grep opcache_get_status`
- Binary measured: 13.3 MB uncompressed / **3.27 MB gzipped** (includes OPcache, pib, session only)

### Task 2: Patch php-wasm-builder to support OPcache static linking ✅

**Files created/modified:**
- `php-wasm-build/patches/opcache-wasm-support.sh` — Patch script applied during Docker build
- `php-wasm-build/build.sh` — Modified to inject OPcache patches into Makefile flow
- `php-wasm-build/.php-wasm-rc` — Updated documentation for OPcache configuration

**Key decisions:**
1. **PHP 8.5 simplification**: OPcache is mandatory in PHP 8.5 (RFC: make_opcache_required), so the complex static linking patches (ext_shared=no, opcache_module.c glue, shared→no) needed for PHP ≤8.4 are unnecessary. Only two patches are required.
2. **Patch injection approach**: Rather than forking seanmorris/php-wasm or creating a complex git patch with unknown line numbers, we inject a shell script into the Makefile's `patched` target via sed. The script runs inside Docker after base patches and before configure.
3. **mmap(MAP_ANON) is sufficient**: Emscripten's mmap() allocates from the WASM linear heap. Since WASM is single-process, no inter-process shared memory is needed — OPcache only needs process-local memory for its opcode cache.

**Patches applied (via `patches/opcache-wasm-support.sh`):**
1. `php_cv_shm_mmap_anon=no` → `yes` in `ext/opcache/config.m4` — Forces autoconf to recognize mmap(MAP_ANON) support (cross-compilation test can't execute in Emscripten)
2. Add `#include <unistd.h>` in `ext/opcache/zend_accelerator_debug.c` — Provides getpid() declaration for non-Windows (Emscripten)

**Gotchas for future agents:**
- The seanmorris/php-wasm sm-8.5 branch does NOT handle OPcache shared memory detection for Emscripten. Without our patches, OPcache compiles (because PHP 8.5 makes it mandatory) but the configure warning "No supported shared memory caching support" means it's non-functional at runtime.
- `WITH_OPCACHE=1` in `.php-wasm-rc` is informational only — the sm-8.5 Makefile doesn't read this variable. OPcache is compiled because PHP 8.5 requires it.
- The sed-based Makefile injection in `build.sh` matches the literal text `git apply --no-index patch/php${PHP_VERSION}.patch`. If seanmorris changes their patching mechanism, this will silently fail (OPcache compiles but without shared memory).
- `MAIN_MODULE=0` (static linking) works fine with OPcache on PHP 8.5 since OPcache uses `[no]` (static) in its `PHP_NEW_EXTENSION` call.

### Task 3: Build and measure the new WASM binary
- Rebuild PHP WASM with OPcache statically linked
- Measure binary size increase (gzipped) — must fit within 3MB budget
- If too large, investigate: OPcache JIT disabled (not useful in WASM anyway), strip OPcache debug info, tune OPcache memory allocation
- Compare: baseline (no OPcache) vs OPcache-enabled binary sizes

### Task 4: Configure OPcache ini settings for WASM environment
- Set optimal OPcache ini directives in worker.ts auto_prepend_file or php.ini:
  - opcache.enable=1
  - opcache.enable_cli=1 (CGI mode)
  - opcache.memory_consumption=32 (or lower — tune for WASM memory budget)
  - opcache.max_accelerated_files=1000 (Laravel has ~500-800 files)
  - opcache.validate_timestamps=0 (files never change in MEMFS)
  - opcache.jit=disable (JIT not useful in WASM)
  - opcache.file_cache= (investigate if file-based cache helps in MEMFS)
- Test that OPcache activates correctly (phpinfo() or opcache_get_status())

### Task 5: Benchmark warm request performance
- Baseline: measure current warm request time (multiple runs, p50/p95/p99)
- OPcache: measure warm request time with OPcache enabled
- Target: ~3x improvement (400-650ms → 130-220ms)
- Measure: first request (cold OPcache) vs subsequent requests (warm OPcache)
- Measure: memory usage increase from OPcache

### Task 6: Integration and cleanup
- Update config/laraworker.php to expose OPcache settings
- Update build documentation
- Update MEMORY.md with OPcache findings
- Ensure ClassPreloader still works alongside OPcache (complementary, not conflicting)
- Run full test suite

## Size Budget Impact
| Component | Without OPcache | With OPcache (est.) |
|-----------|----------------|---------------------|
| PHP WASM binary | ~2.6 MB gz | ~2.7-2.9 MB gz |
| OPcache overhead | 0 | ~100-300 KB gz |
| Total impact | — | +100-300 KB |

OPcache's C code is relatively small. The main size concern is the shared memory allocation at runtime (linear memory), not the binary size.

## Success Criteria
- [ ] OPcache statically linked and functional in PHP WASM
- [ ] Binary size increase ≤ 300 KB gzipped
- [ ] Warm request speedup ≥ 2x (ideally 3x)
- [ ] opcache_get_status() shows cache hits on second request
- [ ] All existing tests pass
- [ ] Total bundle still under 3 MB gzipped