# Laraworker

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

Deploy Laravel applications to Cloudflare Workers via PHP WASM.

Laraworker packages your Laravel app into a compressed tar archive, then runs it on Cloudflare Workers using [php-cgi-wasm](https://github.com/nicmart/php-cgi-wasm). On each request, the Worker unpacks the app into an in-memory filesystem and processes it through the PHP WASM runtime — no traditional server required.

## Requirements

- PHP 8.2+
- Laravel 12
- Node.js or [Bun](https://bun.sh)
- A [Cloudflare](https://cloudflare.com) account

## Installation

```bash
composer require kieranbrown/laraworker
```

Then run the install command to configure your project:

```bash
php artisan laraworker:install
```

This will:
- Publish the `config/laraworker.php` configuration file
- Add build scripts and dependencies to `package.json`
- Update `.gitignore` to exclude the `.laraworker/` build directory
- Install npm dependencies (`php-cgi-wasm`, `wrangler`, etc.)
- Run an initial build into `.laraworker/`

## Configuration

After installation, configure your setup in `config/laraworker.php`:

```php
return [
    // PHP extensions to include in the WASM bundle
    'extensions' => [
        'mbstring' => true,   // ~742 KiB gzipped
        'openssl' => true,    // ~936 KiB gzipped
    ],

    // Directories included in the app bundle (relative to project root)
    'include_dirs' => [
        'app',
        'bootstrap',
        'config',
        'database',
        'routes',
        'resources/views',
        'vendor',
    ],

    // Individual files to include
    'include_files' => [
        'public/index.php',
        'artisan',
        'composer.json',
    ],

    // Regex patterns for files to exclude
    'exclude_patterns' => [
        '/\.git\//',
        '/\/node_modules\//',
        '/\/tests\//',
        // ...
    ],
];
```

### Wrangler Configuration

Deployment settings like `worker_name`, `account_id`, and `compatibility_date` are configured in `config/laraworker.php`. The `wrangler.jsonc` is auto-generated into `.laraworker/` at build time from these config values.

## Usage

Laraworker provides five Artisan commands:

### `laraworker:install`

Configure the project for Cloudflare Workers and run an initial build.

```bash
php artisan laraworker:install
php artisan laraworker:install --force  # Overwrite existing files
```

### `laraworker:build`

Build the Laravel application for production deployment. Caches config, routes, and views, then packages everything into `app.tar.gz`.

```bash
php artisan laraworker:build
```

### `laraworker:dev`

Build and start a local development server using `wrangler dev`.

```bash
php artisan laraworker:dev
```

### `laraworker:deploy`

Build and deploy to Cloudflare Workers.

```bash
php artisan laraworker:deploy
php artisan laraworker:deploy --dry-run  # Build without deploying
```

### `laraworker:status`

Check installation status, bundle sizes, and tier compatibility.

```bash
php artisan laraworker:status
```

## How It Works

1. **Build** — Your Laravel app is optimized (cached config/routes/views, classmap autoload) and packaged into a compressed `app.tar.gz`
2. **Deploy** — The archive and PHP WASM binary are deployed to Cloudflare Workers as static assets
3. **Request** — On cold start, the Worker fetches `app.tar.gz`, unpacks it into an in-memory filesystem (MEMFS), and boots the PHP runtime
4. **Process** — Each HTTP request is routed through `php-cgi-wasm`, which executes your Laravel application and returns the response

The entire bundle (WASM binary + app archive + Worker code) fits within Cloudflare Workers' **free tier limit of 3 MB**.

## Custom Domain Setup

To serve your app from a custom domain, configure your Cloudflare account with the domain and set up routes. The `wrangler.jsonc` is auto-generated at build time from `config/laraworker.php` — for advanced wrangler settings, you can customize the `stubs/wrangler.jsonc.stub` template.

Make sure your domain is added to your Cloudflare account and DNS is configured.

## Extensions

Two PHP extensions can be toggled in `config/laraworker.php`:

| Extension | Default | Size Impact |
|-----------|---------|-------------|
| `mbstring` | Enabled | ~742 KiB gzipped |
| `openssl` | Enabled | ~936 KiB gzipped |

Disabling unused extensions reduces your bundle size, which can help stay within Cloudflare's tier limits.

## License

Laraworker is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
