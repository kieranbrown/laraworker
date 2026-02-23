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
- **CLI**: artisan commands `laraworker:install`, `laraworker:build`, `laraworker:dev`, `laraworker:deploy`, `laraworker:status`
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
- **OPcache configuration**: `config/laraworker.php` exposes OPcache settings — OPcache caches compiled PHP opcodes in WASM linear memory for ~3x warm request speedup. Works alongside ClassPreloader (complementary optimizations). See `.fuel/docs/MEMORY.md` for details

## Quality Gates
| Tool | Command | Purpose |
|------|---------|---------|
| Pint | `vendor/bin/pint --dirty` | PHP code formatter (Laravel preset, no custom config) |
| Pest | `vendor/bin/pest --compact` | PHP test runner (Feature + Unit suites via orchestra/testbench) |
| Fuel quality-gate | `.fuel/quality-gate` | Pre-commit hook: Pint auto-fix on staged PHP files → restage → full Pest suite |

## Recent Changes
- 2026-02-23: Statically linked OPcache into custom PHP WASM binary — caches compiled opcodes in WASM linear memory, directly addresses cold-start bottleneck (parsing all framework files without OPcache)
- 2026-02-23: Added OPcache configuration to `config/laraworker.php` — exposes OPcache settings for WASM environment with sensible defaults for ~3x warm request speedup
- 2026-02-23: Upgraded custom PHP WASM build from 8.2.11 to 8.5; added aggressive bundle size minimization (parallel whitespace stripping, additional vendor file pruning) targeting <3MB Cloudflare Workers free tier
- 2026-02-22: Eliminated `.cloudflare/` from user's app — all worker assets now generated into `.laraworker/` at build time; added `BuildDirectory` helper (664ea16)
- 2026-02-22: Simplified `InstallCommand` — removed stub copying/generation; build now owns all file generation (b9b0658)
- 2026-02-22: Added build-time optimizations to `BuildCommand.php` — whitespace stripping, vendor pruning, SP stripping, class preloading (de0f23e)
_Last updated: 2026-02-23_
