# Memory Efficiency Epic — e-422e63

## Problem

The uncompressed app.tar.gz is **63 MB**, which gets extracted into MEMFS inside the **64 MB WASM linear memory**. This leaves virtually zero room for OPcache (configured for 28 MB) or PHP runtime heap. Result: Worker resource exceeded on heavier pages like /dashboard/login.

### Memory Budget (current)

| Component | Size | Notes |
|-----------|------|-------|
| WASM linear memory | 64 MB | Fixed, no growth |
| MEMFS (app tar uncompressed) | ~63 MB | Way too large |
| OPcache SHM | 24+4 MB | Configured but can't allocate |
| PHP runtime heap | ??? | Virtually nothing left |
| **CF Workers isolate limit** | **128 MB** | JS heap + WASM combined |

### Root Causes

1. **`vendor/kieranbrown/laraworker/` (15 MB)** bundled in tar — includes the 13 MB WASM binary and 1.2 MB .mjs that PHP never accesses
2. **`vendor/blade-ui-kit/blade-heroicons/` (5 MB)** — raw SVG source files; only cached Blade views are needed at runtime
3. **No vendor file pruning** — tests, docs, changelogs, READMEs, license files, .git dirs all shipped
4. **No exclusion of non-PHP runtime files** from vendor packages

### Target Budget

| Component | Target | Notes |
|-----------|--------|-------|
| MEMFS (app tar) | ~20-25 MB | Strip 40+ MB of waste |
| OPcache SHM | 24+4 MB | Now fits comfortably |
| PHP runtime heap | ~10-15 MB | Enough for Filament |
| **Total WASM** | **~64 MB** | Fits budget |

---

## Tasks

### Task 1: Exclude laraworker package internals from app tar (HIGH — saves ~15 MB)
**Complexity: simple**

In `stubs/build-app.mjs`, add tar exclusions for:
- `vendor/kieranbrown/laraworker/php-wasm-build/` (13 MB WASM + build tooling)
- `vendor/kieranbrown/laraworker/playground/` (test app)
- `vendor/kieranbrown/laraworker/stubs/` (build stubs, not needed at runtime)
- `vendor/kieranbrown/laraworker/dist/` if present
- `vendor/kieranbrown/laraworker/*.wasm`
- `vendor/kieranbrown/laraworker/*.mjs`

Only the PHP source (`src/`, `config/`, `routes/`) and `composer.json` are needed at runtime.

### Task 2: Strip non-runtime files from all vendor packages (HIGH — saves ~10-15 MB)
**Complexity: moderate**

In `stubs/build-app.mjs`, exclude from ALL vendor packages:
- `*/tests/`, `*/Tests/`, `*/.github/`, `*/.git/`
- `*/docs/`, `*/doc/`, `*/documentation/`
- `*/*.md` (except LICENSE), `*/CHANGELOG*`, `*/UPGRADING*`
- `*/phpunit.xml*`, `*/phpstan*`, `*/.phpcs*`
- `*/composer.lock` (only composer.json needed)
- `*/.editorconfig`, `*/.gitattributes`, `*/.gitignore`
- `*/Makefile`, `*/Dockerfile`

### Task 3: Strip unused Blade icon SVG source files (MEDIUM — saves ~5 MB)
**Complexity: moderate**

After `view:cache` runs, the compiled Blade views are in `storage/framework/views/`. The raw SVG component files in `vendor/blade-ui-kit/blade-heroicons/resources/` are no longer needed. Exclude them from the tar.

Similarly check for other icon packages that ship raw SVGs.

### Task 4: Audit and optimize vendor package inclusion (MEDIUM — saves ~5-10 MB)
**Complexity: moderate**

Profile the top 20 vendor packages by size. For each, identify what PHP actually needs at runtime vs what's dead weight. Key targets:
- `vendor/filament/` (25 MB) — likely ships JS/CSS source that's already in public/
- `vendor/symfony/` (5.9 MB) — may include unused components
- `vendor/livewire/` (4.3 MB) — check for bundled JS source

Create package-level exclusions for non-PHP files that are already served as static assets.

### Task 5: Add MEMFS size monitoring and budget enforcement (LOW)
**Complexity: simple**

During build, after tar extraction count:
- Report uncompressed MEMFS size in the build report (already shows compressed)
- Warn if uncompressed size exceeds a configurable threshold (default: 30 MB)
- Show top 10 directories by size for debugging

### Task 6: Verify OPcache works end-to-end after memory savings
**Complexity: simple**

After implementing the above, deploy to Cachet and verify:
- /dashboard/login loads without Worker resource exceeded
- OPcache status endpoint shows healthy hit rates
- Warm requests are consistently <100ms