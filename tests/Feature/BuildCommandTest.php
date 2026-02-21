<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->cloudflareDir = base_path('.cloudflare');

    if (! is_dir($this->cloudflareDir)) {
        mkdir($this->cloudflareDir, 0755, true);
    }
});

afterEach(function () {
    // Clean up generated files
    $files = [
        base_path('.cloudflare/.env.production'),
        base_path('.cloudflare/build-config.json'),
    ];

    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    if (is_dir($this->cloudflareDir) && count(glob($this->cloudflareDir.'/*')) === 0) {
        rmdir($this->cloudflareDir);
    }
});

test('build command fails when cloudflare directory is missing', function () {
    if (is_dir($this->cloudflareDir)) {
        // Remove the directory for this test
        array_map('unlink', glob($this->cloudflareDir.'/*'));
        rmdir($this->cloudflareDir);
    }

    $this->artisan('laraworker:build')
        ->expectsOutputToContain('Laraworker not installed')
        ->assertExitCode(1);
});

test('build command generates env production when missing', function () {
    // Create a .env file for the generation to work from
    $envPath = base_path('.env');
    $hadEnv = file_exists($envPath);
    if (! $hadEnv) {
        file_put_contents($envPath, "APP_NAME=Test\nAPP_ENV=local\nAPP_DEBUG=true\n");
    }

    $envProduction = base_path('.cloudflare/.env.production');

    expect(file_exists($envProduction))->toBeFalse();

    // The command will fail at build script stage (no build-app.mjs), but
    // .env.production should be generated before that
    $this->artisan('laraworker:build');

    expect(file_exists($envProduction))->toBeTrue();

    $contents = file_get_contents($envProduction);
    expect($contents)
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('LOG_CHANNEL=stderr')
        ->toContain('SESSION_DRIVER=array')
        ->toContain('CACHE_STORE=array');

    if (! $hadEnv) {
        unlink($envPath);
    }
});

test('build command skips env generation when env production exists', function () {
    $envProduction = base_path('.cloudflare/.env.production');
    file_put_contents($envProduction, "APP_ENV=production\n");

    $originalContents = file_get_contents($envProduction);

    $this->artisan('laraworker:build');

    // File should remain unchanged
    expect(file_get_contents($envProduction))->toBe($originalContents);
});

test('fix cached paths replaces base path with /app', function () {
    $cacheDir = base_path('bootstrap/cache');
    if (! is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $basePath = base_path();
    $cachedFile = $cacheDir.'/config.php';

    // Simulate a cached config file with local paths
    $content = "<?php return ['path' => '{$basePath}/storage', 'base' => '{$basePath}'];";
    file_put_contents($cachedFile, $content);

    // Use reflection to test fixCachedPaths
    $command = new \Laraworker\Console\BuildCommand;
    $method = new ReflectionMethod($command, 'fixCachedPaths');

    $method->invoke($command, $cachedFile);

    $result = file_get_contents($cachedFile);
    expect($result)
        ->toContain('/app/storage')
        ->toContain("'base' => '/app'")
        ->not->toContain($basePath);

    unlink($cachedFile);
});

test('parse env file returns key value pairs', function () {
    $envFile = base_path('.cloudflare/.env.test-parse');
    file_put_contents($envFile, implode("\n", [
        'APP_ENV=production',
        'APP_DEBUG=false',
        '# This is a comment',
        '',
        'APP_KEY=base64:test',
    ]));

    $command = new \Laraworker\Console\BuildCommand;
    $method = new ReflectionMethod($command, 'parseEnvFile');

    $result = $method->invoke($command, $envFile);

    expect($result)->toBe([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_KEY' => 'base64:test',
    ]);

    unlink($envFile);
});
