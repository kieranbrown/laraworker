<?php

test('command is registered and callable', function () {
    // Command should exist - just verify it runs
    // With default worker_name from config, it will try to use wrangler
    $this->artisan('laraworker:delete', ['--force' => true])
        ->assertSuccessful();
});

test('safety guard blocks deleting production worker without force', function () {
    config(['laraworker.worker_name' => 'laraworker']);

    $this->artisan('laraworker:delete')
        ->expectsOutputToContain('Refusing to delete the production "laraworker" worker')
        ->assertFailed();
});

test('safety guard allows deletion with force flag', function () {
    config(['laraworker.worker_name' => 'laraworker']);

    // With --force, it should proceed past the safety guard
    // Wrangler is available in test environment, so this should succeed
    $this->artisan('laraworker:delete', ['--force' => true])
        ->expectsOutputToContain("Deleting worker 'laraworker'")
        ->assertSuccessful();
});

test('normal worker name proceeds without force with confirmation', function () {
    config(['laraworker.worker_name' => 'laraworker-playground-abc123']);

    // Should ask for confirmation, then proceed
    // Wrangler is available, so it should succeed after confirmation
    $this->artisan('laraworker:delete')
        ->expectsConfirmation("Are you sure you want to delete worker 'laraworker-playground-abc123'?", 'yes')
        ->expectsOutputToContain("Deleting worker 'laraworker-playground-abc123'")
        ->assertSuccessful();
});

test('deletion can be cancelled', function () {
    config(['laraworker.worker_name' => 'laraworker-playground-abc123']);

    $this->artisan('laraworker:delete')
        ->expectsConfirmation("Are you sure you want to delete worker 'laraworker-playground-abc123'?", 'no')
        ->expectsOutputToContain('Deletion cancelled.')
        ->assertSuccessful();
});
