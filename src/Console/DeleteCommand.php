<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DeleteCommand extends Command
{
    protected $signature = 'laraworker:delete
                            {--force : Skip confirmation}';

    protected $description = 'Delete the deployed Cloudflare Worker';

    public function handle(): int
    {
        /** @var string|null $workerName */
        $workerName = config('laraworker.worker_name');

        if (empty($workerName)) {
            $this->components->error('Worker name is not configured. Set LARAWORKER_NAME in your .env file.');

            return self::FAILURE;
        }

        // Safety guard for production worker
        if ($workerName === 'laraworker' && ! $this->option('force')) {
            $this->components->error('Refusing to delete the production "laraworker" worker. Use --force to override.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->confirm("Are you sure you want to delete worker '{$workerName}'?")) {
                $this->components->info('Deletion cancelled.');

                return self::SUCCESS;
            }
        }

        // Check wrangler availability
        $checkProcess = new Process(['npx', 'wrangler', '--version'], base_path(), null, null, 30);
        $checkProcess->run();

        if (! $checkProcess->isSuccessful()) {
            $this->components->error('Wrangler not found. Install with: npm install -g wrangler');

            return self::FAILURE;
        }

        // Check authentication
        $authProcess = new Process(['npx', 'wrangler', 'whoami'], base_path(), null, null, 30);
        $authProcess->run();

        if (! $authProcess->isSuccessful()) {
            $this->components->error('Wrangler not authenticated. Run: npx wrangler login');

            return self::FAILURE;
        }

        $this->components->info("Deleting worker '{$workerName}'...");

        /** @var string|null $accountId */
        $accountId = config('laraworker.account_id');

        $env = [];
        if (! empty($accountId)) {
            $env['CLOUDFLARE_ACCOUNT_ID'] = $accountId;
        }

        $process = new Process(
            ['npx', 'wrangler', 'delete', '--name', $workerName],
            base_path(),
            $env,
            null,
            300
        );

        $output = '';
        $process->run(function (string $type, string $buffer) use (&$output): void {
            $output .= $buffer;
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            // Check if worker not found
            if (str_contains($output, 'not found') || str_contains($output, 'does not exist') || str_contains($output, 'Could not find')) {
                $this->components->warn("Worker '{$workerName}' not found — it may have already been deleted.");

                return self::SUCCESS;
            }

            $this->components->error('Deletion failed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Worker '{$workerName}' deleted successfully.");

        return self::SUCCESS;
    }
}
