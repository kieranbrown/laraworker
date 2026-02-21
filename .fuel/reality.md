# Reality

## Architecture
Laravel 12 app running on Cloudflare Workers via PHP-WASM. Custom PHP 8.2.11 WASM binary (static-linked, MAIN_MODULE=0) with minimal extensions (ctype, filter, tokenizer). App is tarred/gzipped, uploaded as Static Asset, unpacked into MEMFS on first request. Missing PHP functions (iconv, openssl, mb_split) provided via stubs injected at runtime.

## Modules
| Module | Purpose | Entry Point |
|--------|---------|-------------|
| `.cloudflare/` | Worker runtime — request handling, MEMFS init, tar unpack, PHP stubs, WASM config | `worker.ts` |
| `packages/laraworker/` | Composer package — artisan commands (install, build, dev, deploy), extension registry, config | `LaraworkerServiceProvider.php` |
| `app/` | Laravel application code — models, controllers, providers | `bootstrap/app.php` |
| `routes/` | HTTP and console route definitions | `web.php`, `console.php` |
| `config/` | Laravel configuration (10 files) | Loaded by framework |
| `database/` | Migrations, factories, seeders | `migrations/` |
| `resources/` | Blade views, Tailwind CSS, JS | `views/welcome.blade.php` |
| `php-wasm-build/` | Docker toolchain for building custom PHP WASM binary | Makefile |
| `tests/` | Pest test suites (Feature + Unit) | `Pest.php` |

## Entry Points
- **HTTP (production)**: `.cloudflare/worker.ts` → fetches app.tar.gz → unpacks to MEMFS → routes to `public/index.php` via PhpCgiCloudflare
- **HTTP (local)**: `php artisan serve` → `public/index.php`
- **CLI**: `artisan` — `laraworker:install`, `laraworker:build`, `laraworker:dev`, `laraworker:deploy`
- **Frontend build**: `vite.config.js` — entry points `resources/css/app.css`, `resources/js/app.js`

## Patterns
- **Laravel 12 streamlined structure**: `bootstrap/app.php` configures routing/middleware/exceptions (no Kernel.php)
- **Constructor property promotion** for DI
- **Pest v4** for testing with `Tests\TestCase` base class
- **Tailwind CSS v4** via `@tailwindcss/vite` plugin (no tailwind.config.js)
- **Path repository**: `packages/laraworker` symlinked into vendor via composer
- **Extension system**: `laraworker:install` generates `php.ts` with dynamic WASM extension imports
- **PHP stubs**: Runtime-injected via `auto_prepend_file` for functions missing from minimal WASM build
- **Config caching**: Baked paths replaced from local → `/app` after `config:cache`

## Quality Gates
| Tool | Command | Purpose |
|------|---------|---------|
| Pest | `php artisan test --compact` | PHP test runner (Feature + Unit suites) |
| Laravel Pint | `vendor/bin/pint --dirty --format agent` | PHP code formatter (PSR-12 based) |
| Vite | `bun run build` | Frontend asset bundling (CSS/JS) |
| Wrangler | `wrangler deploy` | Cloudflare Workers deployment |
| Composer | `composer test` (`config:clear && php artisan test`) | Composer test script |

## Recent Changes
_Last updated: 2026-02-21_
