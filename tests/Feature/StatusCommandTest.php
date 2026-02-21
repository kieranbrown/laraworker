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

test('status command succeeds', function () {
    $this->artisan('laraworker:status')
        ->assertSuccessful();
});

test('status shows not installed when .cloudflare missing', function () {
    $this->artisan('laraworker:status')
        ->expectsOutputToContain('Installation Status')
        ->assertSuccessful();
});

test('status shows installed when .cloudflare exists', function () {
    mkdir($this->cloudflareDir, 0755, true);
    file_put_contents($this->cloudflareDir.'/wrangler.jsonc', '{}');

    $this->artisan('laraworker:status')
        ->expectsOutputToContain('Installation Status')
        ->assertSuccessful();
});

test('status shows configuration section', function () {
    $this->artisan('laraworker:status')
        ->expectsOutputToContain('Configuration')
        ->assertSuccessful();
});

test('status shows bundle sizes section', function () {
    $this->artisan('laraworker:status')
        ->expectsOutputToContain('Bundle Sizes')
        ->assertSuccessful();
});

test('status shows tier compatibility section', function () {
    $this->artisan('laraworker:status')
        ->expectsOutputToContain('Tier Compatibility')
        ->assertSuccessful();
});

test('status shows performance hints section', function () {
    $this->artisan('laraworker:status')
        ->expectsOutputToContain('Performance Hints')
        ->assertSuccessful();
});
