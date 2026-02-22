<?php

use Illuminate\Support\Facades\File;
use Laraworker\BuildDirectory;

beforeEach(function () {
    $this->laraworkerDir = base_path(BuildDirectory::DIRECTORY);

    if (is_dir($this->laraworkerDir)) {
        File::deleteDirectory($this->laraworkerDir);
    }
});

afterEach(function () {
    if (is_dir($this->laraworkerDir)) {
        File::deleteDirectory($this->laraworkerDir);
    }
});

test('status command succeeds', function () {
    $this->artisan('laraworker:status')
        ->assertSuccessful();
});

test('status shows not installed when .laraworker missing', function () {
    $this->artisan('laraworker:status')
        ->expectsOutputToContain('Installation Status')
        ->assertSuccessful();
});

test('status shows installed when .laraworker exists', function () {
    mkdir($this->laraworkerDir, 0755, true);
    file_put_contents($this->laraworkerDir.'/wrangler.jsonc', '{}');

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
