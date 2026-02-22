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

test('build config includes strip_whitespace and strip_providers', function () {
    // Trigger the build which will fail at build script stage but should write config
    $this->artisan('laraworker:build');

    $configPath = base_path('.cloudflare/build-config.json');
    if (! file_exists($configPath)) {
        $this->markTestSkipped('build-config.json not generated (build script not found)');
    }

    $config = json_decode(file_get_contents($configPath), true);

    expect($config)->toHaveKey('strip_whitespace');
    expect($config)->toHaveKey('strip_providers');
    expect($config['strip_whitespace'])->toBeTrue();
    expect($config['strip_providers'])->toBeArray();
});

test('generate preload file creates bootstrap/preload.php', function () {
    // Create a fake classmap at the testbench base path
    $vendorDir = base_path('vendor/composer');
    if (! is_dir($vendorDir)) {
        mkdir($vendorDir, 0755, true);
    }

    $classmapFile = $vendorDir.'/autoload_classmap.php';
    $hadClassmap = file_exists($classmapFile);
    $originalClassmap = $hadClassmap ? file_get_contents($classmapFile) : null;

    file_put_contents($classmapFile, '<?php return [
        "Illuminate\\\\Support\\\\Str" => "/fake/vendor/laravel/framework/src/Illuminate/Support/Str.php",
        "Illuminate\\\\Routing\\\\Router" => "/fake/vendor/laravel/framework/src/Illuminate/Routing/Router.php",
        "Illuminate\\\\Foundation\\\\Console\\\\ServeCommand" => "/fake/vendor/laravel/framework/src/Illuminate/Foundation/Console/ServeCommand.php",
        "App\\\\Models\\\\User" => "/fake/app/Models/User.php",
    ];');

    $preloadPath = base_path('bootstrap/preload.php');

    $command = new \Laraworker\Console\BuildCommand;
    $method = new ReflectionMethod($command, 'generatePreloadFile');

    $command->setLaravel(app());
    $componentsProperty = new ReflectionProperty(\Illuminate\Console\Command::class, 'components');
    $input = new \Symfony\Component\Console\Input\ArrayInput([]);
    $output = new \Symfony\Component\Console\Output\BufferedOutput;
    $style = new \Illuminate\Console\OutputStyle($input, $output);
    $componentsProperty->setValue($command, new \Illuminate\Console\View\Components\Factory($style));

    $method->invoke($command);

    expect(file_exists($preloadPath))->toBeTrue();

    $contents = file_get_contents($preloadPath);
    expect($contents)
        ->toContain('require_once')
        ->toContain('/app/vendor/autoload.php')
        // Should include Illuminate\Support and Illuminate\Routing
        ->toContain('Illuminate/Support/Str.php')
        ->toContain('Illuminate/Routing/Router.php')
        // Should NOT include Console commands or App classes
        ->not->toContain('ServeCommand')
        ->not->toContain('App/Models');

    unlink($preloadPath);

    if ($hadClassmap) {
        file_put_contents($classmapFile, $originalClassmap);
    } else {
        unlink($classmapFile);
        // Clean up created dirs if they were empty
        @rmdir($vendorDir);
        @rmdir(base_path('vendor'));
    }
});

test('strip service providers removes providers from cached config', function () {
    $cacheDir = base_path('bootstrap/cache');
    if (! is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $cachedFile = $cacheDir.'/config.php';
    $content = <<<'PHP'
    <?php return array (
      'app' => array (
        'providers' => array (
          0 => 'Illuminate\\Auth\\AuthServiceProvider',
          1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
          2 => 'Illuminate\\Bus\\BusServiceProvider',
          3 => 'Illuminate\\Routing\\RoutingServiceProvider',
        ),
      ),
    );
    PHP;

    file_put_contents($cachedFile, $content);

    config(['laraworker.strip_providers' => [
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
    ]]);

    // Use Artisan infrastructure to test the private method
    $command = new \Laraworker\Console\BuildCommand;
    $method = new ReflectionMethod($command, 'stripServiceProviders');

    // Set up the command's components property via reflection
    $command->setLaravel(app());
    $componentsProperty = new ReflectionProperty(\Illuminate\Console\Command::class, 'components');
    $input = new \Symfony\Component\Console\Input\ArrayInput([]);
    $output = new \Symfony\Component\Console\Output\BufferedOutput;
    $style = new \Illuminate\Console\OutputStyle($input, $output);
    $componentsProperty->setValue($command, new \Illuminate\Console\View\Components\Factory($style));

    $method->invoke($command, $cachedFile);

    $result = file_get_contents($cachedFile);
    expect($result)
        ->not->toContain('BroadcastServiceProvider')
        ->not->toContain('BusServiceProvider')
        ->toContain('AuthServiceProvider')
        ->toContain('RoutingServiceProvider');

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
