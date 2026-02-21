<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DeployCommand extends Command
{
    protected $signature = 'laraworker:deploy';

    protected $description = 'Build and deploy the Laravel application to Cloudflare Workers';

    public function handle(): int
    {
        if (! is_dir(base_path('.cloudflare'))) {
            $this->components->error('Laraworker not installed. Run: php artisan laraworker:install');

            return self::FAILURE;
        }

        // Run build first
        $buildExitCode = $this->call('laraworker:build');
        if ($buildExitCode !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->components->info('Deploying to Cloudflare Workers...');
        $this->newLine();

        $process = new Process(
            ['npx', 'wrangler', 'deploy'],
            base_path('.cloudflare'),
            null,
            null,
            300
        );

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->components->error('Deployment failed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Deployed successfully!');

        return self::SUCCESS;
    }
}
