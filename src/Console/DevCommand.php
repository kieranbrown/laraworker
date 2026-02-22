<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Laraworker\BuildDirectory;
use Symfony\Component\Process\Process;

class DevCommand extends Command
{
    protected $signature = 'laraworker:dev';

    protected $description = 'Build and start the Cloudflare Workers dev server';

    public function handle(): int
    {
        // Run build first
        $buildExitCode = $this->call('laraworker:build');
        if ($buildExitCode !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->components->info('Starting wrangler dev server...');
        $this->newLine();

        $buildDir = new BuildDirectory;

        $process = new Process(
            ['npx', 'wrangler', 'dev'],
            $buildDir->path(),
            null,
            null,
            null // No timeout — long-running server
        );

        $process->setTty(Process::isTtySupported());

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
