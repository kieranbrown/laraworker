<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PHP Extensions
    |--------------------------------------------------------------------------
    |
    | Enable or disable PHP WASM extensions. Each extension adds to the bundle
    | size. Only enable what your application actually needs.
    |
    | mbstring (~742 KiB gzipped) — Multi-byte string functions
    | openssl  (~936 KiB gzipped) — OpenSSL encryption/decryption
    |
    */

    'extensions' => [
        'mbstring' => true,
        'openssl' => true,
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
        \Illuminate\Broadcasting\BroadcastServiceProvider::class,
        \Illuminate\Bus\BusServiceProvider::class,
        \Illuminate\Notifications\NotificationServiceProvider::class,
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

];
