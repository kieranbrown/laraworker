<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PHP Extensions
    |--------------------------------------------------------------------------
    |
    | When set to true, the extension is expected to be compiled into the WASM
    | binary. When false, PHP stub functions are generated at build time.
    |
    | The default WASM binary does NOT include these extensions. Set to true
    | only if you build a custom WASM binary with the extension compiled in.
    |
    | mbstring — Multi-byte string functions (mb_split, mb_strlen, etc.)
    | openssl  — OpenSSL encryption/decryption (openssl_encrypt, etc.)
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Public Static Assets
    |--------------------------------------------------------------------------
    |
    | When enabled, files in public/ (except index.php and the build/ directory)
    | are copied to Cloudflare Static Assets at build time. This allows files
    | like robots.txt, favicon.ico, and other static resources to be served
    | directly from Cloudflare's edge without invoking the PHP WASM worker.
    |
    */

    'public_assets' => true,

    'extensions' => [
        'mbstring' => false,
        'openssl' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | OPcache Configuration
    |--------------------------------------------------------------------------
    |
    | OPcache settings for the PHP WASM runtime. OPcache caches compiled PHP
    | opcodes in WASM linear memory, providing ~3x speedup for warm requests.
    | These settings are tuned for the Cloudflare Workers environment where
    | files never change (MEMFS) and memory is limited.
    |
    | Note: OPcache must be statically linked into the PHP WASM binary.
    |       This is handled by the php-wasm-builder toolchain.
    |
    */

    'opcache' => [
        'enabled' => true,
        'enable_cli' => true,
        'memory_consumption' => 16,
        'interned_strings_buffer' => 4,
        'max_accelerated_files' => 1000,
        'validate_timestamps' => false,
        'jit' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Strip PHP Whitespace
    |--------------------------------------------------------------------------
    |
    | When enabled, PHP files in the bundle are stripped of comments and
    | unnecessary whitespace at build time using `php -w`. This reduces
    | tar size by ~30-40% per file and speeds up PHP parsing in WASM.
    |
    | Note: This adds significant build time (several minutes) so it's
    | disabled by default to keep builds fast. Enable for production
    | deployments where bundle size is critical.
    |
    */

    'strip_whitespace' => true,

    /*
    |--------------------------------------------------------------------------
    | Service Providers to Strip
    |--------------------------------------------------------------------------
    |
    | Service providers listed here are removed from the cached config at
    | build time. This eliminates registration and boot overhead for services
    | that aren't functional in a Cloudflare Workers environment.
    |
    | Typical candidates: Broadcasting, Queue, and other providers that
    | require infrastructure not available in Workers (WebSockets, Redis, etc).
    |
    */

    'strip_providers' => [
        'Illuminate\Broadcasting\BroadcastServiceProvider',
        'Illuminate\Bus\BusServiceProvider',
        'Illuminate\Notifications\NotificationServiceProvider',
        'Laravel\Pail\PailServiceProvider',
    ],

    /*
    |--------------------------------------------------------------------------
    | Included Directories
    |--------------------------------------------------------------------------
    |
    | Directories from the Laravel project root to include in the app bundle.
    | These are packed into app.tar.gz and unpacked into MEMFS at runtime.
    |
    */

    'include_dirs' => [
        'app',
        'bootstrap',
        'config',
        'routes',
        'resources/views',
        'storage/framework/views',
        'vendor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Included Files
    |--------------------------------------------------------------------------
    |
    | Individual files from the project root to include in the bundle.
    |
    */

    'include_files' => [
        'public/index.php',
        'artisan',
        'composer.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude Patterns
    |--------------------------------------------------------------------------
    |
    | Regex patterns for paths to exclude from the bundle. Matched against
    | the relative path prefixed with '/'.
    |
    */

    'exclude_patterns' => [
        '/\\.git\\//',
        '/\\.github\\//',
        '/\\/node_modules\\//',
        '/\\/tests\\//',
        '/\\/\\.DS_Store$/',
        '/\\/\\.editorconfig$/',
        '/\\/\\.gitattributes$/',
        '/\\/\\.gitignore$/',
        '/\\/phpunit\\.xml$/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Inertia SSR
    |--------------------------------------------------------------------------
    |
    | Enable inline Inertia server-side rendering within the Cloudflare Worker.
    | When enabled, HTML responses containing Inertia page data are intercepted
    | and rendered using Vue/React SSR before being sent to the client.
    |
    | This eliminates the need for a separate SSR server — the worker performs
    | SSR inline after PHP generates the response.
    |
    | Requirements:
    | - A Vite SSR build that outputs a bundle importable by the worker
    | - The SSR bundle must export a `render(page)` function
    | - Additional ~200-500 KiB bundle size for Vue/React SSR runtime
    |
    | Supported frameworks: 'vue', 'react'
    |
    */

    'inertia' => [
        'ssr' => env('LARAWORKER_INERTIA_SSR', false),
        'framework' => env('LARAWORKER_INERTIA_FRAMEWORK', 'vue'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment
    |--------------------------------------------------------------------------
    |
    | Configuration for deploying your Laravel application to Cloudflare Workers.
    | These settings are used when generating wrangler.jsonc and during the
    | build process for production deployments.
    |
    */

    'worker_name' => env('LARAWORKER_NAME', \Illuminate\Support\Str::slug(config('app.name', 'laravel'))),

    'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),

    'compatibility_date' => env('LARAWORKER_COMPAT_DATE'),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Custom domain routes for the Cloudflare Worker. Each entry is a route
    | object with a `pattern` key and optional `custom_domain` flag. When
    | set, these are included in the generated wrangler.jsonc.
    |
    | Example:
    |   [['pattern' => 'example.com', 'custom_domain' => true]]
    |
    */

    'routes' => [],

    'd1_databases' => [],

    'env_overrides' => [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'LOG_CHANNEL' => 'stderr',
        'SESSION_DRIVER' => 'cookie',
        'CACHE_STORE' => 'array',
    ],

];
