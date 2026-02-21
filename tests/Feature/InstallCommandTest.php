<?php

use Illuminate\Support\Facades\File;

function cleanUpInstallTest(): void
{
    $dirs = [base_path('.cloudflare')];
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
    $this->cloudflareDir = base_path('.cloudflare');
    cleanUpInstallTest();
});

afterEach(function () {
    cleanUpInstallTest();
});

test('install creates .cloudflare directory', function () {
    // Provide package.json so updatePackageJson doesn't fail
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\nAPP_ENV=local\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    expect(is_dir($this->cloudflareDir))->toBeTrue();
});

test('install generates worker.ts, php.ts, and wrangler.jsonc', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    expect(file_exists($this->cloudflareDir.'/worker.ts'))->toBeTrue()
        ->and(file_exists($this->cloudflareDir.'/php.ts'))->toBeTrue()
        ->and(file_exists($this->cloudflareDir.'/wrangler.jsonc'))->toBeTrue();
});

test('install publishes stub files', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    $expectedFiles = ['worker.ts', 'shims.ts', 'tar.ts', 'build-app.mjs', 'tsconfig.json'];

    foreach ($expectedFiles as $file) {
        expect(file_exists($this->cloudflareDir.'/'.$file))
            ->toBeTrue("Expected {$file} to exist in .cloudflare/");
    }
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

test('install does not overwrite existing files without force flag', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    // Create .cloudflare with a custom worker.ts
    mkdir($this->cloudflareDir, 0755, true);
    file_put_contents($this->cloudflareDir.'/worker.ts', '// custom content');
    file_put_contents($this->cloudflareDir.'/wrangler.jsonc', '// custom wrangler');

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    // worker.ts should not be overwritten
    expect(file_get_contents($this->cloudflareDir.'/worker.ts'))
        ->toBe('// custom content');
    expect(file_get_contents($this->cloudflareDir.'/wrangler.jsonc'))
        ->toBe('// custom wrangler');
});

test('install with --force flag overwrites existing files', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    // Create .cloudflare with a custom worker.ts
    mkdir($this->cloudflareDir, 0755, true);
    file_put_contents($this->cloudflareDir.'/worker.ts', '// custom content');

    $this->artisan('laraworker:install', ['--force' => true])
        ->assertSuccessful();

    // worker.ts should be overwritten with the stub content
    expect(file_get_contents($this->cloudflareDir.'/worker.ts'))
        ->not->toBe('// custom content');
});

test('install generates .env.production', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\nAPP_ENV=local\nAPP_DEBUG=true\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    $envPath = $this->cloudflareDir.'/.env.production';
    expect(file_exists($envPath))->toBeTrue();

    $content = file_get_contents($envPath);
    expect($content)
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('LOG_CHANNEL=stderr')
        ->toContain('SESSION_DRIVER=array')
        ->toContain('CACHE_STORE=array');
});

test('install generates php.ts with extension imports', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");

    // Enable mbstring extension
    config(['laraworker.extensions' => ['mbstring' => true, 'openssl' => false]]);

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    $phpTs = file_get_contents($this->cloudflareDir.'/php.ts');
    expect($phpTs)
        ->toContain('mbstring')
        ->toContain('libonig');
});

test('install updates .gitignore', function () {
    file_put_contents(base_path('package.json'), json_encode(['name' => 'test']));
    file_put_contents(base_path('.env'), "APP_NAME=Test\n");
    file_put_contents(base_path('.gitignore'), "/vendor\n/node_modules\n");

    $this->artisan('laraworker:install')
        ->assertSuccessful();

    $gitignore = file_get_contents(base_path('.gitignore'));
    expect($gitignore)
        ->toContain('/.cloudflare/dist/')
        ->toContain('.env.production');
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
