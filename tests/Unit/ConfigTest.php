<?php

test('config has default extensions', function () {
    $config = config('laraworker.extensions');

    expect($config)
        ->toBeArray()
        ->toHaveKey('mbstring')
        ->toHaveKey('openssl');
});

test('config default extensions are enabled', function () {
    expect(config('laraworker.extensions.mbstring'))->toBeTrue();
    expect(config('laraworker.extensions.openssl'))->toBeTrue();
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
    config(['laraworker.extensions.mbstring' => false]);

    expect(config('laraworker.extensions.mbstring'))->toBeFalse();
    expect(config('laraworker.extensions.openssl'))->toBeTrue();
});

test('config exclude patterns are valid regex', function () {
    $patterns = config('laraworker.exclude_patterns');

    foreach ($patterns as $pattern) {
        // preg_match returns false on error
        $result = @preg_match($pattern, '');
        expect($result)->not->toBeFalse("Invalid regex pattern: {$pattern}");
    }
});
