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
            $this->buildDirectory->generatePhpTs();
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

        $this->components->task('Preparing production vendor (no-dev)', function () use ($basePath) {
            return $this->prepareProductionVendor($basePath);
        });
    }

    /**
     * Create a staging directory with production-only vendor dependencies.
     *
     * This runs composer update --no-dev --classmap-authoritative in isolation
     * to ensure no dev packages (faker, phpunit, pest, psysh) end up in the bundle.
     * The --classmap-authoritative flag skips filesystem checks, critical for WASM
     * where FS operations are expensive.
     */
    private function prepareProductionVendor(string $basePath): bool
    {
        $stagingDir = $this->buildDirectory->path('vendor-staging');

        // Clean up any previous staging directory
        if (is_dir($stagingDir)) {
            $this->recursiveRmdir($stagingDir);
        }

        mkdir($stagingDir, 0755, true);

        // Copy composer.json to staging, resolving relative path repository URLs
        // so they work from the staging directory instead of only from $basePath
        $composerJson = json_decode(file_get_contents($basePath.'/composer.json'), true);
        if (isset($composerJson['repositories'])) {
            foreach ($composerJson['repositories'] as &$repo) {
                if (($repo['type'] ?? '') === 'path' && isset($repo['url'])) {
                    if (! str_starts_with($repo['url'], '/')) {
                        $resolved = realpath($basePath.'/'.$repo['url']);
                        if ($resolved !== false) {
                            $repo['url'] = $resolved;
                        }
                    }

                    // Force mirroring instead of symlinks so staging is self-contained
                    $repo['options']['symlink'] = false;
                }
            }
            unset($repo);
        }
        file_put_contents($stagingDir.'/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        // Copy the project's autoload source directories (app/, database/, etc.) into the
        // staging directory. Without these, Composer's --classmap-authoritative flag generates
        // a classmap that only covers vendor packages, missing all App\* classes. At runtime,
        // the authoritative classloader won't fall back to PSR-4 directory scanning, so any
        // attempt to load App\Providers\AppServiceProvider (or any app class) causes a fatal error.
        $this->copyAutoloadSources($composerJson, $basePath, $stagingDir);

        // Run composer update (not install) because the lock file contains relative path
        // repository source paths that don't resolve from the staging directory
        $process = new Process(
            ['composer', 'update', '--no-dev', '--optimize-autoloader', '--classmap-authoritative', '--no-scripts', '--no-interaction'],
            $stagingDir,
            null,
            null,
            300
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $this->components->warn('Composer update failed in staging directory.');
            $stderr = $process->getErrorOutput();
            if ($stderr) {
                $this->components->warn($stderr);
            }

            return false;
        }

        // Write staging path to build config for build-app.mjs
        $buildConfigPath = $this->buildDirectory->path('build-config.json');
        $config = json_decode(file_get_contents($buildConfigPath), true);
        $config['vendor_staging_dir'] = $stagingDir;
        file_put_contents($buildConfigPath, json_encode($config, JSON_PRETTY_PRINT)."\n");

        return true;
    }

    /**
     * Copy the project's autoload source directories into the staging directory.
     *
     * Composer's --classmap-authoritative only uses the classmap (no PSR-4 fallback).
     * The classmap is built by scanning directories declared in composer.json's autoload
     * section. Since the staging directory only contains vendor/, the app's own classes
     * (App\*, Database\*) would be missing from the classmap without this step.
     *
     * @param  array<string, mixed>  $composerJson
     */
    private function copyAutoloadSources(array $composerJson, string $basePath, string $stagingDir): void
    {
        $autoload = $composerJson['autoload'] ?? [];

        foreach ($autoload['psr-4'] ?? [] as $paths) {
            foreach ((array) $paths as $path) {
                $src = $basePath.'/'.rtrim($path, '/');
                $dst = $stagingDir.'/'.rtrim($path, '/');

                if (is_dir($src) && ! is_dir($dst)) {
                    $this->copyDirectoryRecursive($src, $dst);
                }
            }
        }

        foreach ($autoload['classmap'] ?? [] as $path) {
            $src = $basePath.'/'.rtrim($path, '/');
            $dst = $stagingDir.'/'.rtrim($path, '/');

            if (is_dir($src) && ! is_dir($dst)) {
                $this->copyDirectoryRecursive($src, $dst);
            } elseif (is_file($src) && ! file_exists($dst)) {
                $dir = dirname($dst);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                copy($src, $dst);
            }
        }

        foreach ($autoload['files'] ?? [] as $file) {
            $src = $basePath.'/'.$file;
            $dst = $stagingDir.'/'.$file;

            if (is_file($src) && ! file_exists($dst)) {
                $dir = dirname($dst);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                copy($src, $dst);
            }
        }
    }

    /**
     * Recursively copy a directory.
     */
    private function copyDirectoryRecursive(string $source, string $dest): void
    {
        mkdir($dest, 0755, true);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $destPath = $dest.'/'.$iterator->getSubPathname();

            if ($item->isDir()) {
                if (! is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destPath);
            }
        }
    }

    /**
     * Recursively remove a directory.
     */
    private function recursiveRmdir(string $dir): void
    {
        if (is_link($dir)) {
            unlink($dir);

            return;
        }

        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir.'/'.$object;
                    if (is_link($path) || ! is_dir($path)) {
                        unlink($path);
                    } else {
                        $this->recursiveRmdir($path);
                    }
                }
            }
            rmdir($dir);
        }
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
            600
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
