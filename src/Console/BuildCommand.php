<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Laraworker\BuildDirectory;
use Symfony\Component\Process\Process;

class BuildCommand extends Command
{
    protected $signature = 'laraworker:build';

    protected $description = 'Build the Laravel application for Cloudflare Workers';

    private BuildDirectory $buildDirectory;

    public function handle(): int
    {
        $this->components->info('Building for Cloudflare Workers...');

        $this->buildDirectory = new BuildDirectory;

        $this->components->task('Preparing build directory', function () {
            $this->buildDirectory->ensureDirectory();
            $this->buildDirectory->copyStubs();
            $this->buildDirectory->generatePhpTs(config('laraworker.extensions', []));
            $this->buildDirectory->generateWranglerConfig();
            $this->buildDirectory->generateEnvProduction();
            $this->buildDirectory->writeBuildConfig();

            return true;
        });

        try {
            $this->optimizeForProduction();

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

    private function optimizeForProduction(): void
    {
        $this->components->info('Optimizing for production...');

        $basePath = base_path();
        $envFile = $this->buildDirectory->path('.env.production');

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

        $this->stripServiceProviders(base_path('bootstrap/cache/config.php'));

        $this->generatePreloadFile();

        $this->components->task('Optimizing autoloader', function () use ($basePath) {
            // --classmap-authoritative skips filesystem checks for classes not in classmap
            // Critical for WASM where filesystem operations are expensive
            $process = new Process(
                ['composer', 'dump-autoload', '--no-dev', '--optimize', '--classmap-authoritative', '--no-scripts'],
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

        // Restore autoloader FIRST before running any artisan commands
        // This ensures dev dependencies are available when clearing caches
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

        $this->components->task('Clearing config cache', function () use ($basePath) {
            return $this->runArtisan(['config:clear'], $basePath);
        });

        $this->components->task('Clearing route cache', function () use ($basePath) {
            return $this->runArtisan(['route:clear'], $basePath);
        });

        $this->components->task('Clearing view cache', function () use ($basePath) {
            return $this->runArtisan(['view:clear'], $basePath);
        });

        // Clean up generated preload file
        $preloadPath = base_path('bootstrap/preload.php');
        if (file_exists($preloadPath)) {
            unlink($preloadPath);
        }
    }

    /**
     * Generate a class preload file that requires critical framework files upfront.
     *
     * This eliminates per-class autoloader lookups and MEMFS filesystem overhead
     * in the WASM runtime by loading all framework classes in a single batch.
     */
    private function generatePreloadFile(): void
    {
        $this->components->task('Generating class preload file', function () {
            $classmapPath = base_path('vendor/composer/autoload_classmap.php');
            if (! file_exists($classmapPath)) {
                return false;
            }

            $classmap = require $classmapPath;
            $basePath = base_path();

            // Namespaces to preload — core framework classes needed for web requests
            $preloadPrefixes = [
                'Illuminate\\Auth\\',
                'Illuminate\\Container\\',
                'Illuminate\\Config\\',
                'Illuminate\\Cookie\\',
                'Illuminate\\Encryption\\',
                'Illuminate\\Events\\',
                'Illuminate\\Filesystem\\',
                'Illuminate\\Foundation\\',
                'Illuminate\\Hashing\\',
                'Illuminate\\Http\\',
                'Illuminate\\Log\\',
                'Illuminate\\Pipeline\\',
                'Illuminate\\Routing\\',
                'Illuminate\\Session\\',
                'Illuminate\\Support\\',
                'Illuminate\\Translation\\',
                'Illuminate\\Validation\\',
                'Illuminate\\View\\',
            ];

            // Namespaces to skip even if they match a prefix above
            $skipPrefixes = [
                'Illuminate\\Foundation\\Console\\',
                'Illuminate\\Foundation\\Testing\\',
                'Illuminate\\Routing\\Console\\',
                'Illuminate\\Auth\\Console\\',
            ];

            $files = [];
            foreach ($classmap as $class => $file) {
                $matched = false;
                foreach ($preloadPrefixes as $prefix) {
                    if (str_starts_with($class, $prefix)) {
                        $matched = true;
                        break;
                    }
                }

                if (! $matched) {
                    continue;
                }

                foreach ($skipPrefixes as $skipPrefix) {
                    if (str_starts_with($class, $skipPrefix)) {
                        $matched = false;
                        break;
                    }
                }

                if ($matched) {
                    // Convert to WASM runtime path
                    $runtimePath = str_replace($basePath, '/app', $file);
                    $files[$runtimePath] = true;
                }
            }

            $files = array_keys($files);
            sort($files);

            $lines = ["<?php\n"];
            $lines[] = '// Auto-generated class preloader — loaded via auto_prepend_file';
            $lines[] = '// Eliminates per-class autoloader lookups in WASM runtime';
            $lines[] = '// Generated: '.date('Y-m-d H:i:s');
            $lines[] = sprintf('// Classes: %d files', count($files));
            $lines[] = '';
            $lines[] = "require_once '/app/vendor/autoload.php';";
            $lines[] = '';

            foreach ($files as $file) {
                $lines[] = "require_once '{$file}';";
            }

            $preloadPath = base_path('bootstrap/preload.php');
            file_put_contents($preloadPath, implode("\n", $lines)."\n");

            return true;
        });
    }

    /**
     * Remove unnecessary service providers from the cached config.
     */
    private function stripServiceProviders(string $cachedConfigFile): void
    {
        /** @var array<int, class-string> $providers */
        $providers = config('laraworker.strip_providers', []);

        if (empty($providers) || ! file_exists($cachedConfigFile)) {
            return;
        }

        $this->components->task('Stripping unnecessary service providers', function () use ($cachedConfigFile, $providers) {
            $contents = file_get_contents($cachedConfigFile);

            foreach ($providers as $provider) {
                // Cached config files use double backslashes in class names
                $doubleEscaped = str_replace('\\', '\\\\', $provider);
                $escaped = preg_quote($doubleEscaped, '/');
                $contents = preg_replace(
                    "/\s*\d+\s*=>\s*'{$escaped}',?\n?/",
                    '',
                    $contents
                );
            }

            file_put_contents($cachedConfigFile, $contents);

            return true;
        });

        $this->components->bulletList(
            collect($providers)->map(fn (string $p) => class_basename($p))->all()
        );
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
            ['node', $this->buildDirectory->path('build-app.mjs')],
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
}
