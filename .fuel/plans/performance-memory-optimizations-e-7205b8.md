# Performance & Memory Optimizations

## Context
Comprehensive analysis identified multiple optimization opportunities across the build pipeline, WASM runtime, and worker configuration. This epic focuses on the highest-impact changes that reduce cold start time, runtime memory usage, and bundle size within the 128 MB Cloudflare Workers memory budget.

## High Impact Tasks

### 1. TAR Unpacker: Zero-Copy with subarray() + Reuse TextDecoder
- **File**: `stubs/tar.ts`
- **Change**: Replace `data.slice()` (line 55) with `data.subarray()` for zero-copy file data views. Move `new TextDecoder()` (line 70) to module scope for single allocation.
- **Impact**: ~1-2 MB memory savings during cold-start extraction of 500+ files
- **Complexity**: simple

### 2. Strip Additional Non-Functional Service Providers
- **File**: `config/laraworker.php` (default config)
- **Change**: Add `Illuminate\Mail\MailServiceProvider` and `Illuminate\Queue\QueueServiceProvider` to the default `strip_providers` list with comments explaining they're non-functional in Workers (no SMTP, no persistent job store)
- **Impact**: ~150-200 KB vendor reduction + faster bootstrap
- **Complexity**: simple

### 3. Build Pipeline: Single-Pass TAR Stats Instead of Triple Creation
- **File**: `stubs/build-app.mjs` (lines 1596-1616)
- **Change**: Track file counts and sizes during the single tar creation pass instead of creating 3 separate tars (full, vendor-only, app-only) just for reporting
- **Impact**: ~15-25% build time reduction
- **Complexity**: moderate

### 4. Gzip Compression Level: 9 → 6 ✅
- **File**: `stubs/build-app.mjs` (line 1597)
- **Change**: Changed zlib level from 9 to 6. Level 6 is 30-50% faster with <2% size increase
- **Impact**: Faster builds, slightly faster cold start decompression
- **Complexity**: trivial
- **Commit**: 9d690880

### 5. Inertia SSR: Single-Pass HTML Replacement
- **File**: `stubs/inertia-ssr.ts` (lines 107-125)
- **Change**: Combine two separate `string.replace()` calls on full HTML + 5 chained entity decode `replace()` calls into a single-pass regex replacement map
- **Impact**: ~5-15ms per SSR request
- **Complexity**: simple

### 6. PHP Bridge: Static Skip Headers Set + Header Memoization ✅
- **File**: `stubs/php.ts.stub` (lines 186-192)
- **Change**: Make the `skip` Set a `static readonly` class property instead of allocating per-request. Pre-compute common HTTP header env name conversions in `HEADER_ENV_CACHE` Map with runtime memoization for new headers.
- **Impact**: ~1-3ms per request, reduced GC pressure
- **Complexity**: simple
- **Commit**: fab8310e

### 7. Build Pipeline: Combined Icon Tree-Shaking Pass ✅
- **File**: `stubs/build-app.mjs` (lines 1378-1449)
- **Change**: Merged the two separate PHP file scans (literal heroicon-* refs + Heroicon:: enum refs) into a single pass
- **Impact**: ~10-15% faster tree-shaking phase
- **Complexity**: simple
- **Commit**: 9d690880

### 8. Wrangler: Reduce Default Observability Sampling
- **File**: `stubs/wrangler.jsonc.stub` (line 17)
- **Change**: Reduce `head_sampling_rate` from 1 (100%) to 0.1 (10%) for production cost savings
- **Impact**: Cost reduction on high-traffic deployments
- **Complexity**: trivial

## Post-Merge Verification
- Run `scripts/playground-smoke-test.sh` to verify no regressions
- Compare cold start times before/after
- Compare bundle size before/after
- Monitor demo site memory headers after deploy