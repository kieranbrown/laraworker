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

    'extensions' => [
        'mbstring' => false,
        'openssl' => false,
    ],

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
        'interned_strings_buffer' => 2,
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
        'database',
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
    | Locales
    |--------------------------------------------------------------------------
    |
    | Locale codes to keep in the vendor bundle. All other locale/language
    | files are stripped at build time. This significantly reduces bundle
    | size — e.g. Filament ships ~13 MB of locale files across 50+ languages.
    |
    | Default: ['en'] — keeps only English translations.
    |
    */

    'locales' => ['en'],

    /*
    |--------------------------------------------------------------------------
    | MEMFS Budget Configuration
    |--------------------------------------------------------------------------
    |
    | The uncompressed app bundle size that can be extracted into MEMFS
    | (WASM virtual filesystem). This is the real memory constraint since
    | the entire tar is extracted into linear memory before PHP runs.
    |
    | Default: 30 MB (Cloudflare Workers WASM has ~64 MB linear memory)
    |
    | When the uncompressed size exceeds this budget, a warning is shown
    | in the build report. Set show_top_dirs=true to see which directories
    | are using the most space.
    |
    */

    'memfs_budget_mb' => 30,

    /*
    |--------------------------------------------------------------------------
    | Show Top Directories
    |--------------------------------------------------------------------------
    |
    | When enabled, the build report includes a list of the top 10 directories
    | by uncompressed size. Useful for debugging which packages are consuming
    | the most MEMFS space.
    |
    */

    'show_top_dirs' => false,

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

    /*
    |--------------------------------------------------------------------------
    | Cloudflare D1 Database Bindings
    |--------------------------------------------------------------------------
    |
    | Configure Cloudflare D1 database bindings for your worker. Each entry
    | maps a binding name to a D1 database. The binding name is used in both
    | wrangler.jsonc (for Cloudflare to inject the D1 handle) and in PHP as
    | the PDO DSN (e.g., new PDO('cfd1:DB')).
    |
    | Create D1 databases via: npx wrangler d1 create <name>
    |
    | Example:
    |   [['binding' => 'DB', 'database_name' => 'my-db', 'database_id' => 'xxx-xxx']]
    |
    */

    'd1_databases' => [],

    'env_overrides' => [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'LOG_CHANNEL' => 'stderr',
        'SESSION_DRIVER' => 'cookie',
        'CACHE_STORE' => 'array',
    ],

];
