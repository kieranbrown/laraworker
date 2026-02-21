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

];
