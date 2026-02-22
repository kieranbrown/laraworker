<?php

use Illuminate\Support\Facades\File;
use Laraworker\BuildDirectory;

function cleanUpInstallTest(): void
{
    $dirs = [base_path('.cloudflare'), base_path(BuildDirectory::DIRECTORY)];
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }

    $files = [
        base_path('package.json'),
        base_path('.gitignore'),
        base_path('.env'),
        config_path('laraworker.php'),
    ];
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

beforeEach(function () {
    cleanUpInstallTest();
});

afterEach(function () {
    cleanUpInstallTest();
});

test('install publishes config', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    expect(file_exists(config_path('laraworker.php')))->toBeTrue();
});

test('install updates package.json with dependencies', function () {
    file_put_contents(base_path('package.json'), json_encode([
        'name' => 'test-app',
        'devDependencies' => [],
    ]));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);

    expect($packageJson['devDependencies'])
        ->toHaveKey('php-cgi-wasm')
        ->toHaveKey('wrangler')
        ->toHaveKey('binaryen');

    expect($packageJson['scripts'])
        ->toHaveKey('build:worker')
        ->toHaveKey('dev:worker')
        ->toHaveKey('deploy:worker');
});

test('install adds extension npm packages when extensions are enabled', function () {
    config(['laraworker.extensions' => ['mbstring' => true, 'openssl' => true]]);

    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    $packageJson = json_decode(file_get_contents(base_path('package.json')), true);

    expect($packageJson['dependencies'])
        ->toHaveKey('php-wasm-mbstring')
        ->toHaveKey('php-wasm-openssl');
});

test('install updates .gitignore with .laraworker entry', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");
    file_put_contents(base_path('.gitignore'), "/vendor\n/node_modules\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    $gitignore = file_get_contents(base_path('.gitignore'));
    expect($gitignore)->toContain('/.laraworker/');
});

test('install does not duplicate .gitignore entry on re-run', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");
    file_put_contents(base_path('.gitignore'), "/vendor\n/.laraworker/\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    $gitignore = file_get_contents(base_path('.gitignore'));
    expect(substr_count($gitignore, '/.laraworker/'))->toBe(1);
});

test('install warns about legacy .cloudflare directory', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    mkdir(base_path('.cloudflare'), 0755, true);

    $this->artisan('laraworker:install')
        ->assertSuccessful()
        ->expectsOutputToContain('Detected .cloudflare/ directory');
});

test('install calls laraworker:build for initial build', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    // Build directory should exist after install (created by laraworker:build)
    expect(is_dir(base_path(BuildDirectory::DIRECTORY)))->toBeTrue();
});
