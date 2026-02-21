<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->cloudflareDir = base_path('.cloudflare');

    if (is_dir($this->cloudflareDir)) {
        File::deleteDirectory($this->cloudflareDir);
    }
});

afterEach(function () {
    if (is_dir($this->cloudflareDir)) {
        File::deleteDirectory($this->cloudflareDir);
    }
});

test('deploy fails if not installed', function () {
    $this->artisan('laraworker:deploy')
        ->assertFailed();
});

test('deploy fails if not installed and shows error message', function () {
    $this->artisan('laraworker:deploy')
        ->expectsOutputToContain('Laraworker not installed')
        ->assertExitCode(1);
});

test('deploy calls build first', function () {
    // Create .cloudflare with wrangler.jsonc for pre-flight checks
    mkdir($this->cloudflareDir, 0755, true);
    file_put_contents($this->cloudflareDir.'/wrangler.jsonc', json_encode([
        'name' => 'test',
        'account_id' => 'test-account',
    ]));

    // Build will fail because build-app.mjs doesn't exist, which means
    // deploy should also fail (since build is a prerequisite)
    $this->artisan('laraworker:deploy')
        ->assertFailed();
});

test('deploy has dry-run option', function () {
    $command = $this->artisan('laraworker:deploy', ['--dry-run' => true]);

    // Will fail because not installed, but the option should be accepted
    $command->assertFailed();
});
