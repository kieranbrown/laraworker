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
            $this->buildDirectory->generateWorkerTs();
            $this->buildDirectory->generatePhpTs();
            $this->buildDirectory->copyWasmBinary();
            $this->buildDirectory->generateWranglerConfig();
            $this->buildDirectory->generateEnvProduction();
            $this->buildDirectory->writeBuildConfig();

            return true;
        });

        try {
            $this->optimizeForProduction();

            $this->buildSsrBundle();

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

        // Ensure storage directories exist before config:cache runs.
        // Laravel's default view.compiled config uses realpath(), which
        // returns false when the directory doesn't exist. This bakes
        // false into the cached config, breaking Blade compilation.
        $this->ensureStorageDirectories($basePath);

        $this->components->task('Caching config', function () use ($basePath, $envFile) {
            return $this->runArtisan(['config:cache', '--env=production'], $basePath, $envFile);
        });

        // view:cache must run BEFORE fixCachedPaths so it can find view files
        // at their local paths. With view.relative_hash=true (set by the service
        // provider), compiled filenames use relative paths and are portable.
        $this->components->task('Caching views', function () use ($basePath, $envFile) {
            return $this->runArtisan(['view:cache'], $basePath, $envFile);
        });

        $this->fixCachedPaths(base_path('bootstrap/cache/config.php'));
        $this->fixCompiledViewPaths();

        $this->components->task('Caching routes', function () use ($basePath, $envFile) {
            return $this->runArtisan(['route:cache', '--env=production'], $basePath, $envFile);
        });

        $this->fixCachedPaths(base_path('bootstrap/cache/routes-v7.php'));

        $this->stripServiceProviders(base_path('bootstrap/cache/config.php'));

        $this->generatePreloadFile();

        $this->cacheFilamentComponents($basePath);

        $this->components->task('Preparing production vendor (no-dev)', function () use ($basePath) {
            return $this->prepareProductionVendor($basePath);
        });

        $this->stripMissingProviders($this->buildDirectory->path('vendor-staging'));
    }

    /**
     * Build the Inertia SSR bundle when SSR is enabled.
     *
     * Runs `npx vite build --ssr` to produce the SSR bundle, then copies
     * it into the .laraworker/ssr/ directory where worker.ts can import it.
     */
    private function buildSsrBundle(): void
    {
        if (! config('laraworker.inertia.ssr')) {
            return;
        }

        $this->newLine();
        $this->components->info('Building Inertia SSR bundle...');

        $this->components->task('Vite SSR build', function () {
            $process = new Process(
                ['npx', 'vite', 'build', '--ssr'],
                base_path(),
                null,
                null,
                300
            );

            $process->run(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

            if (! $process->isSuccessful()) {
                $this->components->warn('SSR build failed: '.$process->getErrorOutput());

                return false;
            }

            return true;
        });

        // Copy SSR bundle to .laraworker/ssr/ for worker import
        $this->components->task('Copying SSR bundle to build directory', function () {
            $ssrSource = base_path('bootstrap/ssr/ssr.js');
            if (! file_exists($ssrSource)) {
                // Try .mjs extension as some Vite configs produce this
                $ssrSource = base_path('bootstrap/ssr/ssr.mjs');
            }

            if (! file_exists($ssrSource)) {
                $this->components->warn('SSR bundle not found at bootstrap/ssr/');

                return false;
            }

            $ssrDir = $this->buildDirectory->path('ssr');
            if (! is_dir($ssrDir)) {
                mkdir($ssrDir, 0755, true);
            }

            copy($ssrSource, $ssrDir.'/ssr.js');

            return true;
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
        $config = file_exists($buildConfigPath) ? json_decode(file_get_contents($buildConfigPath), true) : [];
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
     * Cache Filament components if Filament is installed.
     *
     * Filament's discoverResources/Pages/Widgets performs expensive directory
     * scanning + ReflectionClass instantiation on every request. Running
     * filament:cache-components pre-computes the discovery results into a
     * PHP array at bootstrap/cache/filament/panels/*.php, eliminating this
     * overhead at runtime.
     */
    private function cacheFilamentComponents(string $basePath): void
    {
        // Only run if Filament is installed (check for the artisan command)
        if (! class_exists(\Filament\FilamentServiceProvider::class)) {
            return;
        }

        $this->components->task('Caching Filament components', function () use ($basePath) {
            return $this->runArtisan(['filament:cache-components'], $basePath);
        });
    }

    /**
     * Remove manually specified service providers from the cached config.
     *
     * These are providers that exist in the vendor but aren't functional in
     * a Cloudflare Workers environment (e.g., Broadcasting, Queue).
     */
    private function stripServiceProviders(string $cachedConfigFile): void
    {
        /** @var array<int, class-string> $providers */
        $providers = config('laraworker.strip_providers', []);

        if (empty($providers) || ! file_exists($cachedConfigFile)) {
            return;
        }

        $this->components->task('Stripping unnecessary service providers', function () use ($cachedConfigFile, $providers) {
            $config = require $cachedConfigFile;

            if (isset($config['app']['providers'])) {
                $config['app']['providers'] = array_values(
                    array_diff($config['app']['providers'], $providers)
                );
            }

            file_put_contents(
                $cachedConfigFile,
                '<?php return '.var_export($config, true).';'.PHP_EOL
            );

            return true;
        });

        $this->components->bulletList(
            collect($providers)->map(fn (string $p) => class_basename($p))->all()
        );
    }

    /**
     * Strip providers from cached config and packages.php that don't exist
     * in the production vendor classmap.
     *
     * This catches all dev-only providers (Pail, Sail, Collision, etc.)
     * automatically without maintaining a manual list.
     */
    private function stripMissingProviders(string $stagingDir): void
    {
        $classmapPath = $stagingDir.'/vendor/composer/autoload_classmap.php';
        if (! file_exists($classmapPath)) {
            return;
        }

        $this->components->task('Stripping unavailable service providers', function () use ($classmapPath) {
            $classmap = require $classmapPath;
            $stripped = [];
            $configProviders = [];

            // Strip from cached config (app.providers)
            $configPath = base_path('bootstrap/cache/config.php');
            if (file_exists($configPath)) {
                $config = require $configPath;

                if (isset($config['app']['providers'])) {
                    $original = $config['app']['providers'];
                    $config['app']['providers'] = array_values(
                        array_filter($original, fn (string $provider) => isset($classmap[$provider]))
                    );
                    $stripped = array_diff($original, $config['app']['providers']);
                    $configProviders = $config['app']['providers'];

                    file_put_contents(
                        $configPath,
                        '<?php return '.var_export($config, true).';'.PHP_EOL
                    );
                }
            }

            // Strip from packages.php (auto-discovered providers)
            $packagesPath = base_path('bootstrap/cache/packages.php');
            if (file_exists($packagesPath)) {
                $packages = require $packagesPath;

                foreach ($packages as $name => $package) {
                    foreach ($package['providers'] ?? [] as $provider) {
                        if (! isset($classmap[$provider])) {
                            $stripped[] = $provider;
                            unset($packages[$name]);
                            break;
                        }
                    }
                }

                file_put_contents(
                    $packagesPath,
                    '<?php return '.var_export($packages, true).';'.PHP_EOL
                );
            }

            // Rebuild services.php to match the exact merged provider list that
            // Laravel builds at runtime: config('app.providers') + PackageManifest::providers().
            // ProviderRepository::shouldRecompile() compares services.php['providers']
            // with this merged list. If they differ, Laravel recompiles the manifest
            // on every request — expensive and potentially broken in WASM.
            $servicesPath = base_path('bootstrap/cache/services.php');
            if (file_exists($servicesPath) && ! empty($configProviders)) {
                $services = require $servicesPath;

                // Build the same merged list Laravel uses at runtime:
                // 1. Partition config providers into Illuminate vs non-Illuminate
                // 2. Insert package-discovered providers between them
                $illuminate = array_values(array_filter($configProviders, fn (string $p) => str_starts_with($p, 'Illuminate\\')));
                $nonIlluminate = array_values(array_filter($configProviders, fn (string $p) => ! str_starts_with($p, 'Illuminate\\')));

                $packageProviders = [];
                if (file_exists($packagesPath)) {
                    $pkgs = require $packagesPath;
                    foreach ($pkgs as $pkg) {
                        foreach ($pkg['providers'] ?? [] as $p) {
                            $packageProviders[] = $p;
                        }
                    }
                }

                $mergedProviders = array_values(array_unique(
                    array_merge($illuminate, $packageProviders, $nonIlluminate)
                ));

                $services['providers'] = $mergedProviders;

                // Filter eager, deferred, and when to only include kept providers.
                // Package-discovered providers not yet in services.php are added as eager.
                $providerSet = array_flip($mergedProviders);

                if (isset($services['eager'])) {
                    $services['eager'] = array_values(
                        array_filter($services['eager'], fn (string $p) => isset($providerSet[$p]))
                    );
                }

                if (isset($services['deferred'])) {
                    $services['deferred'] = array_filter(
                        $services['deferred'],
                        fn (string $p) => isset($providerSet[$p])
                    );
                }

                if (isset($services['when'])) {
                    $services['when'] = array_filter(
                        $services['when'],
                        fn (array $events, string $p) => isset($providerSet[$p]),
                        ARRAY_FILTER_USE_BOTH
                    );
                }

                // Add package-discovered providers that aren't yet categorized as eager.
                // These are providers from packages.php that weren't in the original
                // services.php (e.g., because artisan optimize wasn't run with them).
                $categorized = array_merge(
                    $services['eager'] ?? [],
                    array_values($services['deferred'] ?? [])
                );
                $categorizedSet = array_flip($categorized);

                foreach ($mergedProviders as $provider) {
                    if (! isset($categorizedSet[$provider])) {
                        $services['eager'][] = $provider;
                    }
                }

                file_put_contents(
                    $servicesPath,
                    '<?php return '.var_export($services, true).';'.PHP_EOL
                );
            }

            return ! empty($stripped);
        });
    }

    /**
     * Ensure required Laravel storage directories exist.
     *
     * In CI/fresh-checkout environments, storage/framework/views/ may not exist.
     * Laravel's view.compiled config uses realpath(), which returns false for
     * non-existent paths. This false value gets baked into the cached config,
     * making Blade unable to compile or locate views at runtime.
     */
    private function ensureStorageDirectories(string $basePath): void
    {
        $dirs = [
            $basePath.'/storage/framework/views',
            $basePath.'/storage/framework/cache',
            $basePath.'/storage/framework/sessions',
            $basePath.'/storage/logs',
            $basePath.'/bootstrap/cache',
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Replace local base_path references in compiled view files with '/app'.
     *
     * Compiled Blade views contain PATH comments with absolute local paths.
     * These must be rewritten for the WASM runtime.
     */
    private function fixCompiledViewPaths(): void
    {
        $viewsDir = base_path('storage/framework/views');

        if (! is_dir($viewsDir)) {
            return;
        }

        foreach (glob($viewsDir.'/*.php') as $file) {
            $this->fixCachedPaths($file);
        }
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
