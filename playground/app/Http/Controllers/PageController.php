<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function home(): Response
    {
        $opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : null;

        return Inertia::render('Home', [
            'phpVersion' => PHP_VERSION,
            'serverInfo' => php_uname(),
            'laravelVersion' => App::version(),
            'opcacheEnabled' => $opcache !== false && $opcache !== null,
            'opcacheStats' => $opcache['opcache_statistics'] ?? null,
        ]);
    }

    public function performance(): Response
    {
        $opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : null;
        $stats = $opcache['opcache_statistics'] ?? [];
        $memory = $opcache['memory_usage'] ?? [];

        return Inertia::render('Performance', [
            'phpVersion' => PHP_VERSION,
            'opcacheEnabled' => $opcache !== false && $opcache !== null,
            'opcacheStats' => [
                'hits' => $stats['hits'] ?? 0,
                'misses' => $stats['misses'] ?? 0,
                'hitRate' => $stats['opcache_hit_rate'] ?? 0,
                'cachedScripts' => $stats['num_cached_scripts'] ?? 0,
                'cachedKeys' => $stats['num_cached_keys'] ?? 0,
                'maxCachedKeys' => $stats['max_cached_keys'] ?? 0,
            ],
            'memoryUsage' => [
                'usedMemory' => $memory['used_memory'] ?? 0,
                'freeMemory' => $memory['free_memory'] ?? 0,
                'wastedMemory' => $memory['wasted_memory'] ?? 0,
                'wastedPercentage' => $memory['current_wasted_percentage'] ?? 0,
            ],
        ]);
    }

    public function architecture(): Response
    {
        $extensions = get_loaded_extensions();

        return Inertia::render('Architecture', [
            'phpVersion' => PHP_VERSION,
            'extensions' => $extensions,
            'sapi' => PHP_SAPI,
            'opcacheEnabled' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
            'inertiaVersion' => \Composer\InstalledVersions::getPrettyVersion('inertiajs/inertia-laravel'),
        ]);
    }

    public function features(): Response
    {
        $opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : null;

        $features = [
            [
                'name' => 'PHP 8.5 via WebAssembly',
                'description' => 'Full PHP runtime compiled to WASM, running natively in Cloudflare Workers.',
                'status' => 'supported',
            ],
            [
                'name' => 'Laravel 12',
                'description' => 'The latest Laravel framework with all core features working out of the box.',
                'status' => 'supported',
            ],
            [
                'name' => 'Inertia.js SSR',
                'description' => 'Server-side rendered Vue pages for instant page loads and SEO.',
                'status' => 'supported',
            ],
            [
                'name' => 'OPcache',
                'description' => 'Persistent opcode cache across requests for dramatically faster warm responses.',
                'status' => ($opcache !== false && $opcache !== null) ? 'supported' : 'coming-soon',
            ],
            [
                'name' => 'Tailwind CSS v4',
                'description' => 'Utility-first CSS framework with the latest v4 engine, built at deploy time.',
                'status' => 'supported',
            ],
            [
                'name' => 'Static Assets from Edge',
                'description' => 'CSS, JS, images served directly from Cloudflare edge — never touches Workers.',
                'status' => 'supported',
            ],
            [
                'name' => 'Blade Templates',
                'description' => 'Laravel Blade templating engine works fully, including components and directives.',
                'status' => 'supported',
            ],
            [
                'name' => 'Custom WASM Extensions',
                'description' => 'Support for additional PHP extensions compiled to WASM (mbstring, openssl).',
                'status' => 'supported',
            ],
        ];

        return Inertia::render('Features', [
            'features' => $features,
        ]);
    }
}
