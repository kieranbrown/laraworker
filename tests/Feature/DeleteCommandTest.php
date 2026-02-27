<?php

use Laraworker\Console\DeleteCommand;
use Symfony\Component\Process\Process;

/**
 * Registers a testable DeleteCommand that stubs all wrangler subprocess calls.
 */
function fakeDeleteCommand(): void
{
    $fakeCommand = new class extends DeleteCommand
    {
        protected function createProcess(array $command, ?string $cwd = null, ?array $env = null, mixed $input = null, ?float $timeout = null): Process
        {
            $process = Mockery::mock(Process::class);
            $process->shouldReceive('run')->andReturn(0);
            $process->shouldReceive('isSuccessful')->andReturn(true);

            return $process;
        }
    };

    app()->singleton(DeleteCommand::class, fn () => $fakeCommand);
}

test('command is registered and callable', function () {
    fakeDeleteCommand();

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
    fakeDeleteCommand();

    config(['laraworker.worker_name' => 'laraworker']);

    $this->artisan('laraworker:delete', ['--force' => true])
        ->expectsOutputToContain("Deleting worker 'laraworker'")
        ->assertSuccessful();
});

test('normal worker name proceeds without force with confirmation', function () {
    fakeDeleteCommand();

    config(['laraworker.worker_name' => 'laraworker-playground-abc123']);

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
