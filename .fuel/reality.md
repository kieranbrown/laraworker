# Reality

## Architecture
Standalone Composer package (`kieranbrown/laraworker`) for running Laravel on Cloudflare Workers via PHP-WASM. Custom PHP 8.2.11 WASM binary (static-linked, MAIN_MODULE=0) with minimal extensions (ctype, filter, tokenizer). Uses orchestra/testbench for testing.

## Modules
| Module | Purpose | Entry Point |
|--------|---------|-------------|
| `src/` | Package source — ServiceProvider, artisan commands (install, build, dev, deploy) | `LaraworkerServiceProvider.php` |
| `config/` | Package config | `laraworker.php` |
| `stubs/` | Worker stubs — worker.ts, shims.ts, tar.ts, build-app.mjs, php.ts.stub, wrangler.jsonc.stub | Published to user's app |
| `php-wasm-build/` | Docker toolchain for building custom PHP WASM binary | `.php-wasm-rc` |
| `tests/` | Pest test suites (Feature + Unit) using orchestra/testbench | `Pest.php` |

## Entry Points
- **CLI**: artisan commands `laraworker:install`, `laraworker:build`, `laraworker:dev`, `laraworker:deploy`
- **Auto-discovery**: `extra.laravel.providers` registers `Laraworker\LaraworkerServiceProvider`

## Patterns
- **Standalone package**: Root is the Composer package, no Laravel app scaffold
- **Orchestra Testbench**: `Tests\TestCase` extends `Orchestra\Testbench\TestCase` for package testing
- **Pest v3** for testing
- **PSR-4 autoload**: `Laraworker\\` → `src/`, `Tests\\` → `tests/`
- **Extension system**: `laraworker:install` generates `php.ts` with dynamic WASM extension imports
- **PHP stubs**: Runtime-injected via `auto_prepend_file` for functions missing from minimal WASM build

## Quality Gates
| Tool | Command | Purpose |
|------|---------|---------|
| Pest | `vendor/bin/pest --compact` | PHP test runner (Feature + Unit suites) |

## Recent Changes
- Restructured from Laravel app to standalone Composer package (81c88fa)
_Last updated: 2026-02-21_
