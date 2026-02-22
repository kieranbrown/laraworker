<?php

use Illuminate\Filesystem\Filesystem;
use Laraworker\BuildDirectory;

beforeEach(function () {
    $this->buildDir = new BuildDirectory;
    $this->buildPath = base_path(BuildDirectory::DIRECTORY);
    $this->fs = new Filesystem;

    if (is_dir($this->buildPath)) {
        $this->fs->deleteDirectory($this->buildPath);
    }
});

afterEach(function () {
    if (is_dir($this->buildPath)) {
        $this->fs->deleteDirectory($this->buildPath);
    }
});

test('path returns base build directory when called with no arguments', function () {
    expect($this->buildDir->path())->toBe(base_path('.laraworker'));
});

test('path appends relative path to build directory', function () {
    expect($this->buildDir->path('dist/assets'))
        ->toBe(base_path('.laraworker/dist/assets'));
});

test('path strips leading slashes from relative path', function () {
    expect($this->buildDir->path('/worker.ts'))
        ->toBe(base_path('.laraworker/worker.ts'));
});

test('ensureDirectory creates the build directory', function () {
    expect(is_dir($this->buildPath))->toBeFalse();

    $this->buildDir->ensureDirectory();

    expect(is_dir($this->buildPath))->toBeTrue();
});

test('ensureDirectory is idempotent', function () {
    $this->buildDir->ensureDirectory();
    $this->buildDir->ensureDirectory();

    expect(is_dir($this->buildPath))->toBeTrue();
});

test('copyStubs copies all expected stub files', function () {
    $this->buildDir->copyStubs();

    $expectedFiles = [
        'worker.ts',
        'shims.ts',
        'tar.ts',
        'inertia-ssr.ts',
        'build-app.mjs',
        'tsconfig.json',
    ];

    foreach ($expectedFiles as $file) {
        expect(file_exists($this->buildDir->path($file)))
            ->toBeTrue("Expected stub file {$file} to exist");
    }
});

test('copyStubs creates directory if it does not exist', function () {
    expect(is_dir($this->buildPath))->toBeFalse();

    $this->buildDir->copyStubs();

    expect(is_dir($this->buildPath))->toBeTrue();
});

test('copyStubs files match package stubs', function () {
    $this->buildDir->copyStubs();

    $stubDir = dirname(__DIR__, 2).'/stubs';

    expect(file_get_contents($this->buildDir->path('worker.ts')))
        ->toBe(file_get_contents($stubDir.'/worker.ts'));
});

test('generatePhpTs creates php.ts with extension placeholders replaced', function () {
    $this->buildDir->ensureDirectory();
    $this->buildDir->generatePhpTs(['mbstring' => true, 'openssl' => false]);

    $content = file_get_contents($this->buildDir->path('php.ts'));

    expect($content)
        ->toContain('mbstring')
        ->toContain('libonigModule')
        ->toContain('mbstringModule')
        ->not->toContain('{{EXTENSION_IMPORTS}}')
        ->not->toContain('{{SHARED_LIBS}}')
        ->not->toContain('{{PRELOADED_LIBS}}')
        ->not->toContain('{{EXTENSIONS_COMMENT}}')
        ->not->toContain('{{PHP_WASM_IMPORT}}');
});

test('generatePhpTs with no extensions produces clean output', function () {
    $this->buildDir->ensureDirectory();
    $this->buildDir->generatePhpTs([]);

    $content = file_get_contents($this->buildDir->path('php.ts'));

    expect($content)
        ->toContain('Extensions: none')
        ->not->toContain('{{');
});

test('generateWranglerConfig creates wrangler.jsonc from config', function () {
    config(['laraworker.worker_name' => 'test-worker']);
    config(['laraworker.compatibility_date' => '2025-01-01']);

    $this->buildDir->ensureDirectory();
    $this->buildDir->generateWranglerConfig();

    $content = file_get_contents($this->buildDir->path('wrangler.jsonc'));

    expect($content)
        ->toContain('"name": "test-worker"')
        ->toContain('"compatibility_date": "2025-01-01"')
        ->not->toContain('{{APP_NAME}}')
        ->not->toContain('{{COMPATIBILITY_DATE}}');
});

test('generateWranglerConfig uses app name slug when worker_name is null', function () {
    config(['laraworker.worker_name' => null]);
    config(['app.name' => 'My Test App']);

    $this->buildDir->ensureDirectory();
    $this->buildDir->generateWranglerConfig();

    $content = file_get_contents($this->buildDir->path('wrangler.jsonc'));

    expect($content)->toContain('"name": "my-test-app"');
});

test('generateWranglerConfig includes routes when configured', function () {
    config(['laraworker.worker_name' => 'test-worker']);
    config(['laraworker.compatibility_date' => '2025-01-01']);
    config(['laraworker.routes' => [
        ['pattern' => 'example.com', 'custom_domain' => true],
    ]]);

    $this->buildDir->ensureDirectory();
    $this->buildDir->generateWranglerConfig();

    $content = file_get_contents($this->buildDir->path('wrangler.jsonc'));
    $config = json_decode($content, true);

    expect($config['routes'])
        ->toBeArray()
        ->toHaveCount(1)
        ->and($config['routes'][0]['pattern'])->toBe('example.com')
        ->and($config['routes'][0]['custom_domain'])->toBeTrue();
});

test('generateWranglerConfig omits routes when empty', function () {
    config(['laraworker.worker_name' => 'test-worker']);
    config(['laraworker.compatibility_date' => '2025-01-01']);
    config(['laraworker.routes' => []]);

    $this->buildDir->ensureDirectory();
    $this->buildDir->generateWranglerConfig();

    $content = file_get_contents($this->buildDir->path('wrangler.jsonc'));

    expect($content)->not->toContain('"routes"');
});

test('generateEnvProduction creates .env.production with overrides', function () {
    $envPath = base_path('.env');
    $originalEnv = file_exists($envPath) ? file_get_contents($envPath) : null;

    file_put_contents($envPath, "APP_NAME=TestApp\nAPP_ENV=local\nAPP_DEBUG=true\nLOG_CHANNEL=stack\n");

    config([
        'laraworker.env_overrides' => [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'LOG_CHANNEL' => 'stderr',
        ],
    ]);

    try {
        $this->buildDir->ensureDirectory();
        $this->buildDir->generateEnvProduction();

        $content = file_get_contents($this->buildDir->path('.env.production'));

        expect($content)
            ->toContain('APP_NAME=TestApp')
            ->toContain('APP_ENV=production')
            ->toContain('APP_DEBUG=false')
            ->toContain('LOG_CHANNEL=stderr');
    } finally {
        if ($originalEnv !== null) {
            file_put_contents($envPath, $originalEnv);
        } else {
            @unlink($envPath);
        }
    }
});

test('generateEnvProduction appends missing override keys', function () {
    $envPath = base_path('.env');
    $originalEnv = file_exists($envPath) ? file_get_contents($envPath) : null;

    file_put_contents($envPath, "APP_NAME=TestApp\n");

    config([
        'laraworker.env_overrides' => [
            'SESSION_DRIVER' => 'array',
        ],
    ]);

    try {
        $this->buildDir->ensureDirectory();
        $this->buildDir->generateEnvProduction();

        $content = file_get_contents($this->buildDir->path('.env.production'));

        expect($content)
            ->toContain('APP_NAME=TestApp')
            ->toContain('SESSION_DRIVER=array');
    } finally {
        if ($originalEnv !== null) {
            file_put_contents($envPath, $originalEnv);
        } else {
            @unlink($envPath);
        }
    }
});

test('generateEnvProduction does nothing when no .env exists', function () {
    $envPath = base_path('.env');
    $originalEnv = file_exists($envPath) ? file_get_contents($envPath) : null;

    if (file_exists($envPath)) {
        unlink($envPath);
    }

    try {
        $this->buildDir->ensureDirectory();
        $this->buildDir->generateEnvProduction();

        expect(file_exists($this->buildDir->path('.env.production')))->toBeFalse();
    } finally {
        if ($originalEnv !== null) {
            file_put_contents($envPath, $originalEnv);
        }
    }
});

test('writeBuildConfig creates build-config.json from config', function () {
    config([
        'laraworker.extensions' => ['mbstring' => true],
        'laraworker.include_dirs' => ['app', 'config'],
        'laraworker.include_files' => ['artisan'],
        'laraworker.exclude_patterns' => ['/\\.git\\//'],
        'laraworker.strip_whitespace' => true,
        'laraworker.strip_providers' => ['SomeProvider'],
    ]);

    $this->buildDir->ensureDirectory();
    $this->buildDir->writeBuildConfig();

    $json = json_decode(file_get_contents($this->buildDir->path('build-config.json')), true);

    expect($json)
        ->toHaveKey('extensions')
        ->toHaveKey('include_dirs')
        ->toHaveKey('include_files')
        ->toHaveKey('exclude_patterns')
        ->toHaveKey('strip_whitespace')
        ->toHaveKey('strip_providers');

    expect($json['extensions'])->toBe(['mbstring' => true]);
    expect($json['strip_whitespace'])->toBeTrue();
});

test('EXTENSION_REGISTRY contains mbstring and openssl', function () {
    expect(BuildDirectory::EXTENSION_REGISTRY)
        ->toHaveKey('mbstring')
        ->toHaveKey('openssl');

    expect(BuildDirectory::EXTENSION_REGISTRY['mbstring'])
        ->toHaveKey('imports')
        ->toHaveKey('shared_libs')
        ->toHaveKey('preloaded_libs')
        ->toHaveKey('npm_packages');
});

test('DIRECTORY constant is .laraworker', function () {
    expect(BuildDirectory::DIRECTORY)->toBe('.laraworker');
});

test('resolvePhpWasmImport returns fallback when node_modules missing', function () {
    expect($this->buildDir->resolvePhpWasmImport())
        ->toContain('node_modules/php-cgi-wasm/');
});
