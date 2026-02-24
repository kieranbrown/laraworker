<?php

use Illuminate\Support\Facades\File;
use Laraworker\BuildDirectory;

beforeEach(function () {
    $this->buildDir = base_path(BuildDirectory::DIRECTORY);

    if (is_dir($this->buildDir)) {
        File::deleteDirectory($this->buildDir);
    }
});

afterEach(function () {
    if (is_dir($this->buildDir)) {
        File::deleteDirectory($this->buildDir);
    }
});

test('deploy fails if build directory does not exist', function () {
    $this->artisan('laraworker:deploy')
        ->assertFailed();
});

test('deploy fails with warning when wrangler.jsonc is missing', function () {
    // Create .laraworker directory but no wrangler.jsonc
    mkdir($this->buildDir, 0755, true);

    $this->artisan('laraworker:deploy')
        ->expectsOutputToContain('wrangler.jsonc not found')
        ->assertFailed();
});

test('deploy calls build first', function () {
    // Create .laraworker with wrangler.jsonc for pre-flight checks
    mkdir($this->buildDir, 0755, true);
    file_put_contents($this->buildDir.'/wrangler.jsonc', json_encode([
        'name' => 'test',
        'account_id' => 'test-account',
    ]));

    // Replace the build-app.mjs stub temporarily to force build failure
    $stubPath = dirname(dirname(__DIR__)).'/stubs/build-app.mjs';
    $originalContent = file_get_contents($stubPath);
    file_put_contents($stubPath, 'console.error("Build failed for test"); process.exit(1);');

    try {
        // Deploy should fail because build failed
        $this->artisan('laraworker:deploy')
            ->expectsOutputToContain('Build failed')
            ->assertFailed();
    } finally {
        // Restore the original stub
        file_put_contents($stubPath, $originalContent);
    }
});

test('deploy has dry-run option', function () {
    $command = $this->artisan('laraworker:deploy', ['--dry-run' => true]);

    // Will fail because build directory doesn't exist, but the option should be accepted
    $command->assertFailed();
});
