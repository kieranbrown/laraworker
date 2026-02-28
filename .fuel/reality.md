# Reality

## Architecture
Standalone Composer package (`kieranbrown/laraworker`) for running Laravel on Cloudflare Workers via PHP-WASM. Custom PHP 8.5 WASM binary (static-linked, MAIN_MODULE=0) with minimal extensions (ctype, filter, tokenizer, opcache). Uses orchestra/testbench for testing.

## Modules
| Module | Purpose | Entry Point |
|--------|---------|-------------|
| `src/` | Package source — ServiceProvider, artisan commands (install, build, dev, deploy, status) | `LaraworkerServiceProvider.php` |
| `src/BuildDirectory.php` | Helper for `.laraworker/` directory operations (paths, creation, wrangler detection) | `BuildDirectory.php` |
| `config/` | Package config | `laraworker.php` |
| `stubs/` | Worker stubs — worker.ts, shims.ts, tar.ts, build-app.mjs, php.ts.stub, wrangler.jsonc.stub | Published to user's app |
| `php-wasm-build/` | Docker toolchain for building custom PHP WASM binary | `.php-wasm-rc` |
| `tests/` | Pest test suites (Feature + Unit) using orchestra/testbench | `Pest.php` |

## Entry Points
- **CLI**: artisan commands `laraworker:install`, `laraworker:build`, `laraworker:dev`, `laraworker:deploy`, `laraworker:status`, `laraworker:delete`
- **Auto-discovery**: `extra.laravel.providers` registers `Laraworker\LaraworkerServiceProvider`
- **CI/CD**: `.github/workflows/deploy-demo.yml` deploys a demo Laravel app to Cloudflare Workers

## Patterns
- **Standalone package**: Root is the Composer package, no Laravel app scaffold
- **Orchestra Testbench**: `Tests\TestCase` extends `Orchestra\Testbench\TestCase` for package testing
- **Pest v3** for testing
- **PSR-4 autoload**: `Laraworker\\` → `src/`, `Tests\\` → `tests/`
- **Build-time `.laraworker/`**: Worker assets (wrangler config, worker.ts, app.tar.gz) are generated into `.laraworker/` at `laraworker:build` time — not published at install time. `BuildDirectory` helper centralises all path logic.
- **InstallCommand is minimal**: Only registers config/stubs; no worker file copying or generation. All build artifacts live in `.laraworker/`.
- **Extension system**: `laraworker:install` generates `php.ts` with dynamic WASM extension imports
- **PHP stubs**: Runtime-injected via `auto_prepend_file` for functions missing from minimal WASM build
- **Build-time optimizations** (all configurable in `config/laraworker.php`): PHP whitespace stripping via `php -w` (parallel, disabled by default), vendor pruning (CLI bins, translations, test utils, unnecessary file types), service provider stripping from cached config, class preloader for core Illuminate files
- **Deployment isolation**: Playground uses a unique `LARAWORKER_NAME` env var (not `laraworker`) to avoid overwriting the production demo (`laraworker.kswb.dev`). Teardown scripts call `laraworker:delete` to clean up ephemeral workers after testing.
- **OPcache configuration**: `config/laraworker.php` exposes OPcache settings — OPcache caches compiled PHP opcodes in WASM linear memory for warm request speedup. OPcache SHM now persists across requests within an isolate (SAPI lifecycle patched). Works alongside ClassPreloader (complementary optimizations). See `.fuel/docs/performance-investigation.md` for measured results (~17ms warm locally, 781+ hits in production)
- **PhpCgiBase.refresh() guard**: `PhpCgiBase.refresh()` is only called when PHP actually crashes (not on normal 500 errors). Rebuilding the WASM module resets OPcache; the guard preserves OPcache state across normal error responses. Crash detection is based on the Emscripten exit code, not HTTP status.
- **D1 database binding**: `config/laraworker.php` accepts `d1_databases` array; `BuildDirectory` injects D1 bindings into wrangler config and the worker stub exposes them to PHP via `$_ENV`. Uses `seanmorris/pdo-cfd1` PDO driver (statically linked in WASM). Laravel's `database.php` configured with `pdo_cfd1` driver pointing to D1 binding name.
- **MEMFS budget monitoring**: `stubs/build-app.mjs` calculates uncompressed MEMFS size post-build and compares against configurable `memfs_budget` (default 50MB). Build report shows compressed + uncompressed sizes with ✅ FITS / ⚠️ EXCEEDS indicator. Optional `show_top_dirs` config lists top 10 dirs by size.
- **Laraworker self-exclusion**: `stubs/build-app.mjs` excludes laraworker's own non-runtime files from the app tar (php-wasm-build, playground, stubs, dist, scripts, node_modules, dotfiles, *.wasm, *.mjs). Saves ~15MB uncompressed from MEMFS. Only `src/`, `config/`, `routes/`, `resources/`, `composer.json`, `LICENSE` are kept.

## Quality Gates
| Tool | Command | Purpose |
|------|---------|---------|
| Pint | `vendor/bin/pint --dirty` | PHP code formatter (Laravel preset, no custom config) |
| Pest | `vendor/bin/pest --compact` | PHP test runner (Feature + Unit suites via orchestra/testbench) |
| Fuel quality-gate | `.fuel/quality-gate` | Pre-commit hook: Pint auto-fix on staged PHP files → restage → full Pest suite |

## Recent Changes
- 2026-02-28: OPcache 0% hit rate + Error 1102 regression fix: guarded `PhpCgiBase.refresh()` to only rebuild WASM on real crashes (not 500 errors); reduced OPcache `memory_consumption` 24→16 MB to stay within 128 MB CF budget; added `scripts/smoke-test-deployed.sh` for post-deploy verification.
- 2026-02-27: Added MEMFS budget monitoring — build report shows uncompressed MEMFS size with configurable budget (`memfs_budget`, `show_top_dirs` in config). Laraworker self-exclusion from app tar saves ~15MB uncompressed.
- 2026-02-25: Added Cloudflare D1 database support via `seanmorris/pdo-cfd1` — WASM rebuilt with PDO+pdo_cfd1, `BuildDirectory` injects D1 bindings into wrangler config, worker stub exposes bindings to PHP, playground demo added.
- 2026-02-25: Added `laraworker:delete` command — deletes a deployed Cloudflare Worker by name; teardown scripts now call this to prevent orphaned workers. Playground uses unique `LARAWORKER_NAME` (via env) to avoid overwriting production (`laraworker.kswb.dev`).
- 2026-02-24: OPcache truly persists: Patched `cgi_main.c` to skip `php_module_startup()` after first request (if-guard with `_wasm_module_started` flag). OPcache SHM survives between requests within an isolate. Warm requests now ~17ms locally, 781+ hits observed in production.
_Last updated: 2026-02-28_
