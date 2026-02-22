# Upgrade PHP WASM to 8.5 & Minimize Bundle Size

## Goal
Upgrade the custom PHP WASM binary from PHP 8.2 to PHP 8.5.3 and aggressively minimize the total bundle size to stay well under Cloudflare Workers' 3 MB free tier limit.

## Context
- PHP 8.5.3 released Jan 29, 2026 — fully supported in seanmorris/php-wasm ecosystem
- Current build: PHP 8.2, `.php-wasm-rc` → MAIN_MODULE=0, OPTIMIZE=z, ASYNCIFY=1
- Current bundle: ~2.6 MB WASM + ~0.3-0.5 MB app.tar.gz ≈ ~3 MB (tight fit)
- Must stay under 3 MB total gzipped for Cloudflare Workers free tier

## Tasks

### Task 1: Upgrade PHP WASM build to 8.5
- Update `php-wasm-build/.php-wasm-rc`: `PHP_VERSION=8.5`
- Rebuild WASM binary via `php-wasm-build` Docker toolchain
- Measure resulting binary size vs current 8.2 build
- If binary is larger, investigate which 8.5 features add size and whether compile flags can offset
- Update `packages/laraworker/` npm dependency if using versioned php-cgi-wasm packages
- Update platform_check.php override comment (no longer needed if 8.5 >= 8.4 requirement)
- Update Composer platform config to reflect PHP 8.5
- Test: verify Laravel 12 boots and serves a request on the new binary

### Task 2: Audit and strip unnecessary files from app.tar.gz
- Run a full build and dump the tar manifest with file sizes
- Identify the top 50 largest files in the bundle — are they all necessary?
- Audit vendor/ for any dev-only packages that slipped through `--no-dev`
- Add exclusion patterns for:
  - Symfony Resources/ directories (translations, fixtures)
  - Doctrine fixtures/test data
  - Any README.txt, LICENSE files, CREDITS
  - vendor/*/*/src/*/resources/ translation files beyond en
  - Any .json schema files, .xsd, .dtd in vendor
- Reduce Carbon locales from `en*` to just `en` (drop en_AU, en_GB etc. if not needed)
- Measure before/after tar.gz size

### Task 3: Enable whitespace stripping by default for production
- Change `strip_whitespace` default to `true` in `config/laraworker.php`
- Benchmark the build time impact
- If build time is unacceptable (>5 min), investigate parallel stripping or caching
- Measure tar.gz size reduction (expected 5-10%)

### Task 4: Verify and optimize Composer autoloader
- Ensure `composer install --no-dev --optimize-autoloader --classmap-authoritative` is used
- `--classmap-authoritative` skips filesystem checks for classes not in the classmap — faster autoloading in WASM where filesystem ops are expensive
- Verify no dev packages (faker, phpunit, pest, psysh) end up in the final tar
- Measure impact on warm request time

### Task 5: Update documentation and CI
- Update README badges/docs to reflect PHP 8.5
- Update `.github/workflows/deploy-demo.yml` to use PHP 8.5
- Update MEMORY.md and any architecture docs
- Update `packages/laraworker/composer.json` PHP constraint if needed
- Run full test suite to confirm nothing breaks

## Size Budget
| Component | Current | Target |
|-----------|---------|--------|
| PHP WASM binary | ~2.6 MB gz | ≤2.6 MB gz |
| app.tar.gz | ~0.3-0.5 MB gz | ≤0.3 MB gz |
| Worker JS | ~50-100 KB | ~50-100 KB |
| **Total** | **~3.0 MB** | **≤2.9 MB** |

## Success Criteria
- [ ] PHP 8.5.3 WASM binary builds successfully
- [ ] Total bundle ≤ 3 MB gzipped (ideally ≤ 2.9 MB)
- [ ] Laravel 12 boots and serves requests correctly
- [ ] All existing tests pass
- [ ] Build report shows size improvement or parity