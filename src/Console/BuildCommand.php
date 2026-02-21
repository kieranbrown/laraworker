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

        $this->ensureEnvProduction();

        try {
            $this->optimizeForProduction();
            $this->writeBuildConfig();

            $result = $this->runBuildScript();
        } finally {
            $this->restoreLocalEnvironment();
        }

        if ($result !== self::SUCCESS) {
            return $result;
        }

        $this->newLine();
        $this->components->info('Build complete.');

        return self::SUCCESS;
    }

    private function ensureEnvProduction(): void
    {
        $envProduction = base_path('.cloudflare/.env.production');

        if (file_exists($envProduction)) {
            return;
        }

        $this->components->task('Generating .env.production', function () use ($envProduction) {
            $envPath = base_path('.env');
            if (! file_exists($envPath)) {
                $this->components->warn('No .env file found — cannot generate .env.production');

                return false;
            }

            $env = file_get_contents($envPath);
            $overrides = [
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'LOG_CHANNEL' => 'stderr',
                'SESSION_DRIVER' => 'array',
                'CACHE_STORE' => 'array',
            ];

            $lines = explode("\n", $env);
            $result = [];
            $seen = [];

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    $result[] = $line;

                    continue;
                }

                $parts = explode('=', $trimmed, 2);
                $key = $parts[0];

                if (isset($overrides[$key])) {
                    $result[] = "{$key}={$overrides[$key]}";
                    $seen[$key] = true;
                } else {
                    $result[] = $line;
                }
            }

            foreach ($overrides as $key => $value) {
                if (! isset($seen[$key])) {
                    $result[] = "{$key}={$value}";
                }
            }

            file_put_contents($envProduction, implode("\n", $result));
        });
    }

    private function optimizeForProduction(): void
    {
        $this->components->info('Optimizing for production...');

        $basePath = base_path();
        $envFile = base_path('.cloudflare/.env.production');

        $this->components->task('Caching config', function () use ($basePath, $envFile) {
            return $this->runArtisan(['config:cache', '--env=production'], $basePath, $envFile);
        });

        $this->fixCachedPaths(base_path('bootstrap/cache/config.php'));

        $this->components->task('Caching routes', function () use ($basePath, $envFile) {
            return $this->runArtisan(['route:cache', '--env=production'], $basePath, $envFile);
        });

        $this->fixCachedPaths(base_path('bootstrap/cache/routes-v7.php'));

        $this->components->task('Caching views', function () use ($basePath, $envFile) {
            return $this->runArtisan(['view:cache'], $basePath, $envFile);
        });

        $this->components->task('Optimizing autoloader', function () use ($basePath) {
            $process = new Process(
                ['composer', 'dump-autoload', '--classmap-authoritative', '--no-dev'],
                $basePath,
                null,
                null,
                120
            );

            $process->run();

            return $process->isSuccessful();
        });
    }

    private function restoreLocalEnvironment(): void
    {
        $this->newLine();
        $this->components->info('Restoring local environment...');

        $basePath = base_path();

        $this->components->task('Clearing config cache', function () use ($basePath) {
            return $this->runArtisan(['config:clear'], $basePath);
        });

        $this->components->task('Clearing route cache', function () use ($basePath) {
            return $this->runArtisan(['route:clear'], $basePath);
        });

        $this->components->task('Clearing view cache', function () use ($basePath) {
            return $this->runArtisan(['view:clear'], $basePath);
        });

        $this->components->task('Restoring autoloader', function () use ($basePath) {
            $process = new Process(
                ['composer', 'dump-autoload'],
                $basePath,
                null,
                null,
                120
            );

            $process->run();

            return $process->isSuccessful();
        });
    }

    /**
     * Replace local base_path references with WASM-compatible '/app' in cached files.
     */
    private function fixCachedPaths(string $cachedFile): void
    {
        if (! file_exists($cachedFile)) {
            return;
        }

        $contents = file_get_contents($cachedFile);
        $contents = str_replace(base_path(), '/app', $contents);
        file_put_contents($cachedFile, $contents);
    }

    private function runArtisan(array $arguments, string $basePath, ?string $envFile = null): bool
    {
        $command = array_merge([PHP_BINARY, 'artisan'], $arguments);

        $env = null;
        if ($envFile && file_exists($envFile)) {
            $env = $this->parseEnvFile($envFile);
        }

        $process = new Process($command, $basePath, $env, null, 120);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Parse a .env file into an array of environment variables.
     *
     * @return array<string, string>
     */
    private function parseEnvFile(string $path): array
    {
        $env = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $env[$parts[0]] = $parts[1];
            }
        }

        return $env;
    }

    private function runBuildScript(): int
    {
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
