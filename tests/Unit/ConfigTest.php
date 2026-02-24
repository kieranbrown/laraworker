<?php

test('config has default extensions', function () {
    $config = config('laraworker.extensions');

    expect($config)
        ->toBeArray()
        ->toHaveKey('mbstring')
        ->toHaveKey('openssl');
});

test('config default extensions are disabled', function () {
    expect(config('laraworker.extensions.mbstring'))->toBeFalse();
    expect(config('laraworker.extensions.openssl'))->toBeFalse();
});

test('config has default include dirs', function () {
    $dirs = config('laraworker.include_dirs');

    expect($dirs)
        ->toBeArray()
        ->toContain('app')
        ->toContain('bootstrap')
        ->toContain('config')
        ->toContain('routes')
        ->toContain('vendor');
});

test('config has default include files', function () {
    $files = config('laraworker.include_files');

    expect($files)
        ->toBeArray()
        ->toContain('public/index.php')
        ->toContain('artisan')
        ->toContain('composer.json');
});

test('config has default exclude patterns', function () {
    $patterns = config('laraworker.exclude_patterns');

    expect($patterns)->toBeArray()
        ->not->toBeEmpty();
});

test('config merging preserves package defaults', function () {
    // The service provider uses mergeConfigFrom, so package defaults
    // should be available even without publishing
    $extensions = config('laraworker.extensions');

    expect($extensions)->not->toBeNull();
});

test('config can be overridden at runtime', function () {
    config(['laraworker.extensions.mbstring' => true]);

    expect(config('laraworker.extensions.mbstring'))->toBeTrue();
    expect(config('laraworker.extensions.openssl'))->toBeFalse();
});

test('config has inertia ssr settings', function () {
    $inertia = config('laraworker.inertia');

    expect($inertia)
        ->toBeArray()
        ->toHaveKey('ssr')
        ->toHaveKey('framework');
});

test('config inertia ssr is disabled by default', function () {
    expect(config('laraworker.inertia.ssr'))->toBeFalse();
});

test('config inertia framework defaults to vue', function () {
    expect(config('laraworker.inertia.framework'))->toBe('vue');
});

test('config exclude patterns are valid regex', function () {
    $patterns = config('laraworker.exclude_patterns');

    foreach ($patterns as $pattern) {
        // preg_match returns false on error
        $result = @preg_match($pattern, '');
        expect($result)->not->toBeFalse("Invalid regex pattern: {$pattern}");
    }
});

test('config strip_whitespace is enabled by default', function () {
    expect(config('laraworker.strip_whitespace'))->toBeTrue();
});

test('config has strip_providers array', function () {
    $providers = config('laraworker.strip_providers');

    expect($providers)
        ->toBeArray()
        ->toContain(Illuminate\Broadcasting\BroadcastServiceProvider::class)
        ->toContain(Illuminate\Bus\BusServiceProvider::class)
        ->toContain(Illuminate\Notifications\NotificationServiceProvider::class);
});

test('config strip_providers can be customized', function () {
    config(['laraworker.strip_providers' => []]);

    expect(config('laraworker.strip_providers'))->toBeEmpty();
});

test('config has opcache settings', function () {
    $opcache = config('laraworker.opcache');

    expect($opcache)
        ->toBeArray()
        ->toHaveKey('enabled')
        ->toHaveKey('enable_cli')
        ->toHaveKey('memory_consumption')
        ->toHaveKey('max_accelerated_files')
        ->toHaveKey('validate_timestamps')
        ->toHaveKey('jit');
});

test('config opcache defaults are sensible', function () {
    expect(config('laraworker.opcache.enabled'))->toBeTrue();
    expect(config('laraworker.opcache.enable_cli'))->toBeTrue();
    expect(config('laraworker.opcache.memory_consumption'))->toBe(32);
    expect(config('laraworker.opcache.max_accelerated_files'))->toBe(1000);
    expect(config('laraworker.opcache.validate_timestamps'))->toBeFalse();
    expect(config('laraworker.opcache.jit'))->toBeFalse();
});

test('config opcache can be customized', function () {
    config(['laraworker.opcache.enabled' => false]);
    config(['laraworker.opcache.memory_consumption' => 64]);

    expect(config('laraworker.opcache.enabled'))->toBeFalse();
    expect(config('laraworker.opcache.memory_consumption'))->toBe(64);
});
