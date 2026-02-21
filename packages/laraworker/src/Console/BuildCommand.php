<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BuildCommand extends Command
{
    protected $signature = 'laraworker:build';

    protected $description = 'Build the Laravel application for Cloudflare Workers';

    public function handle(): int
    {
        $this->components->info('Building for Cloudflare Workers...');

        if (! is_dir(base_path('.cloudflare'))) {
            $this->components->error('Laraworker not installed. Run: php artisan laraworker:install');

            return self::FAILURE;
        }

        $this->writeBuildConfig();

        $process = new Process(
            ['node', base_path('.cloudflare/build-app.mjs')],
            base_path(),
            null,
            null,
            300
        );

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->components->error('Build failed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Build complete.');

        return self::SUCCESS;
    }

    private function writeBuildConfig(): void
    {
        $config = [
            'extensions' => config('laraworker.extensions', []),
            'include_dirs' => config('laraworker.include_dirs', []),
            'include_files' => config('laraworker.include_files', []),
            'exclude_patterns' => config('laraworker.exclude_patterns', []),
        ];

        file_put_contents(
            base_path('.cloudflare/build-config.json'),
            json_encode($config, JSON_PRETTY_PRINT)."\n"
        );
    }
}
