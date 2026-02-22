<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'laraworker:install
        {--force : Overwrite existing files}';

    protected $description = 'Install Laraworker scaffolding for Cloudflare Workers';

    /** @var array<string, array{imports: string, shared_libs: string[], preloaded_libs: array<string, string>, npm_packages: array<string, string>}> */
    private array $extensionRegistry = [
        'mbstring' => [
            'imports' => <<<'TS'
// @ts-expect-error — wasm import (mbstring dependency)
import libonigModule from './libonig.wasm';
// @ts-expect-error — wasm import
import mbstringModule from './php8.3-mbstring.wasm';
TS,
            'shared_libs' => ['php8.3-mbstring.so'],
            'preloaded_libs' => [
                'libonig.so' => 'libonigModule',
                'php8.3-mbstring.so' => 'mbstringModule',
            ],
            'npm_packages' => [
                'php-wasm-mbstring' => '^0.0.0-c',
            ],
        ],
        'openssl' => [
            'imports' => <<<'TS'
// @ts-expect-error — wasm import (openssl dependency)
import libcryptoModule from './libcrypto.wasm';
// @ts-expect-error — wasm import (openssl dependency)
import libsslModule from './libssl.wasm';
// @ts-expect-error — wasm import
import opensslModule from './php8.3-openssl.wasm';
TS,
            'shared_libs' => ['php8.3-openssl.so'],
            'preloaded_libs' => [
                'libcrypto.so' => 'libcryptoModule',
                'libssl.so' => 'libsslModule',
                'php8.3-openssl.so' => 'opensslModule',
            ],
            'npm_packages' => [
                'php-wasm-openssl' => '^0.0.9-k',
            ],
        ],
    ];

    public function handle(): int
    {
        $this->components->info('Installing Laraworker...');

        $this->publishConfig();
        $this->updatePackageJson();
        $this->updateGitignore();
        $this->installNpmDependencies();
        $this->publishStubs();
        $this->generatePhpTs();
        $this->generateWranglerConfig();
        $this->generateEnvProduction();
        $this->runInitialBuild();
        $this->verifyInstallation();

        $this->newLine();
        $this->components->info('Laraworker installed successfully!');
        $this->components->bulletList([
            'Run <comment>php artisan laraworker:dev</comment> to start the dev server',
            'Run <comment>php artisan laraworker:build</comment> to build for production',
            'Run <comment>php artisan laraworker:deploy</comment> to deploy to Cloudflare',
            'Edit <comment>config/laraworker.php</comment> to configure extensions and included files',
        ]);

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        $this->components->task('Publishing config', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'laraworker-config',
                '--force' => $this->option('force'),
            ]);
        });
    }

    private function publishStubs(): void
    {
        $this->components->task('Publishing worker stubs', function () {
            $stubDir = dirname(__DIR__, 2).'/stubs';
            $targetDir = base_path('.cloudflare');

            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $stubs = ['worker.ts', 'shims.ts', 'tar.ts', 'inertia-ssr.ts', 'build-app.mjs', 'tsconfig.json'];

            foreach ($stubs as $stub) {
                $target = $targetDir.'/'.$stub;
                if (file_exists($target) && ! $this->option('force')) {
                    continue;
                }
                copy($stubDir.'/'.$stub, $target);
            }
        });
    }

    private function generatePhpTs(): void
    {
        $this->components->task('Generating php.ts', function () {
            /** @var array<string, bool> $extensions */
            $extensions = config('laraworker.extensions', []);
            $enabledExtensions = array_keys(array_filter($extensions));

            $stubPath = dirname(__DIR__, 2).'/stubs/php.ts.stub';
            $content = file_get_contents($stubPath);

            // Build template replacements
            $imports = [];
            $sharedLibs = [];
            $preloadedLibs = [];

            foreach ($enabledExtensions as $ext) {
                if (! isset($this->extensionRegistry[$ext])) {
                    continue;
                }

                $reg = $this->extensionRegistry[$ext];
                $imports[] = $reg['imports'];

                foreach ($reg['shared_libs'] as $lib) {
                    $sharedLibs[] = "'{$lib}'";
                }

                foreach ($reg['preloaded_libs'] as $soName => $varName) {
                    $preloadedLibs[] = "        '{$soName}': {$varName},";
                }
            }

            $extensionsComment = empty($enabledExtensions) ? 'none' : implode(', ', $enabledExtensions);
            $importsBlock = empty($imports) ? '' : "\n".implode("\n", $imports);
            $sharedLibsStr = implode(', ', $sharedLibs);
            $preloadedLibsBlock = implode("\n", $preloadedLibs);
            $phpWasmImport = $this->resolvePhpWasmImport();

            $content = str_replace('{{EXTENSIONS_COMMENT}}', $extensionsComment, $content);
            $content = str_replace('{{EXTENSION_IMPORTS}}', $importsBlock, $content);
            $content = str_replace('{{SHARED_LIBS}}', $sharedLibsStr, $content);
            $content = str_replace('{{PRELOADED_LIBS}}', $preloadedLibsBlock, $content);
            $content = str_replace('{{PHP_WASM_IMPORT}}', $phpWasmImport, $content);

            file_put_contents(base_path('.cloudflare/php.ts'), $content);
        });
    }

    private function generateWranglerConfig(): void
    {
        $this->components->task('Generating wrangler.jsonc', function () {
            $target = base_path('.cloudflare/wrangler.jsonc');

            if (file_exists($target) && ! $this->option('force')) {
                return;
            }

            $stubPath = dirname(__DIR__, 2).'/stubs/wrangler.jsonc.stub';
            $content = file_get_contents($stubPath);

            $appName = Str::slug(config('app.name', 'laravel'));
            $date = now()->format('Y-m-d');

            $content = str_replace('{{APP_NAME}}', $appName, $content);
            $content = str_replace('{{COMPATIBILITY_DATE}}', $date, $content);

            file_put_contents($target, $content);
        });
    }

    private function generateEnvProduction(): void
    {
        $this->components->task('Generating .env.production', function () {
            $target = base_path('.cloudflare/.env.production');

            if (file_exists($target) && ! $this->option('force')) {
                return;
            }

            $envPath = base_path('.env');
            if (! file_exists($envPath)) {
                return;
            }

            $env = file_get_contents($envPath);

            // Override settings for Workers production
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

            // Add any overrides not already present
            foreach ($overrides as $key => $value) {
                if (! isset($seen[$key])) {
                    $result[] = "{$key}={$value}";
                }
            }

            file_put_contents($target, implode("\n", $result));
        });
    }

    private function updatePackageJson(): void
    {
        $this->components->task('Updating package.json', function () {
            $packageJsonPath = base_path('package.json');
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            // Add scripts
            $packageJson['scripts'] = array_merge($packageJson['scripts'] ?? [], [
                'build:worker' => 'php artisan laraworker:build',
                'dev:worker' => 'php artisan laraworker:dev',
                'deploy:worker' => 'php artisan laraworker:deploy',
            ]);

            // Add base dependencies from versions config
            $versions = self::npmVersions();
            $packageJson['devDependencies'] = array_merge($packageJson['devDependencies'] ?? [], $versions);

            // Add extension npm packages
            /** @var array<string, bool> $extensions */
            $extensions = config('laraworker.extensions', []);
            foreach (array_keys(array_filter($extensions)) as $ext) {
                if (isset($this->extensionRegistry[$ext])) {
                    foreach ($this->extensionRegistry[$ext]['npm_packages'] as $pkg => $version) {
                        $packageJson['dependencies'] = array_merge($packageJson['dependencies'] ?? [], [
                            $pkg => $version,
                        ]);
                    }
                }
            }

            // Sort dependencies
            if (isset($packageJson['devDependencies'])) {
                ksort($packageJson['devDependencies']);
            }
            if (isset($packageJson['dependencies'])) {
                ksort($packageJson['dependencies']);
            }

            file_put_contents(
                $packageJsonPath,
                json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
            );
        });
    }

    private function updateGitignore(): void
    {
        $this->components->task('Updating .gitignore', function () {
            $gitignorePath = base_path('.gitignore');
            $gitignore = file_exists($gitignorePath) ? file_get_contents($gitignorePath) : '';

            $entries = [
                '/.cloudflare/dist/',
                '/.cloudflare/php-cgi.mjs',
                '/.cloudflare/*.wasm',
                '/.cloudflare/build-config.json',
                '.env.production',
            ];

            $added = false;
            foreach ($entries as $entry) {
                if (! str_contains($gitignore, $entry)) {
                    if (! $added) {
                        $gitignore = rtrim($gitignore)."\n\n# Laraworker\n";
                        $added = true;
                    }
                    $gitignore .= $entry."\n";
                }
            }

            if ($added) {
                file_put_contents($gitignorePath, $gitignore);
            }
        });
    }

    private function installNpmDependencies(): void
    {
        $this->components->task('Installing npm dependencies', function () {
            $packageManager = $this->detectPackageManager();
            $command = match ($packageManager) {
                'bun' => 'bun install --no-frozen-lockfile',
                'yarn' => 'yarn install --no-frozen-lockfile',
                'pnpm' => 'pnpm install --no-frozen-lockfile',
                default => 'npm install',
            };

            exec($command.' 2>&1', $output, $exitCode);

            if ($exitCode !== 0) {
                $this->components->warn("Failed to install npm dependencies. Run '{$command}' manually.");
            }
        });
    }

    private function runInitialBuild(): void
    {
        $this->components->task('Running initial build (patch Emscripten + copy WASM)', function () {
            $this->writeBuildConfig();

            // Use passthru for real-time output to help debugging
            passthru('node '.base_path('.cloudflare/build-app.mjs').' 2>&1', $exitCode);

            if ($exitCode !== 0) {
                $this->components->warn('Initial build failed. Run "php artisan laraworker:build" manually.');
            }
        });
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

    private function verifyInstallation(): void
    {
        $this->newLine();
        $this->components->info('Verifying installation...');

        $files = [
            '.cloudflare/worker.ts',
            '.cloudflare/php.ts',
            '.cloudflare/wrangler.jsonc',
            '.cloudflare/build-app.mjs',
            'config/laraworker.php',
        ];

        $allPresent = true;
        foreach ($files as $file) {
            $exists = file_exists(base_path($file));
            $status = $exists ? '<info>✓</info>' : '<error>✗</error>';
            $this->line("  {$status} {$file}");

            if (! $exists) {
                $allPresent = false;
            }
        }

        if (! $allPresent) {
            $this->components->warn('Some files are missing. Run with --force to regenerate.');
        }
    }

    /**
     * Resolve the WASM import path for php-cgi-wasm.
     *
     * Globs node_modules/php-cgi-wasm/*.wasm to find the actual filename
     * instead of hardcoding a hash that breaks on package updates.
     */
    private function resolvePhpWasmImport(): string
    {
        $wasmDir = base_path('node_modules/php-cgi-wasm');
        $fallback = '../node_modules/php-cgi-wasm/php-cgi.wasm';

        if (! is_dir($wasmDir)) {
            $this->components->warn('php-cgi-wasm not installed yet, using fallback WASM path.');

            return $fallback;
        }

        $wasmFiles = glob($wasmDir.'/*.wasm');

        if (empty($wasmFiles)) {
            $this->components->warn('No .wasm file found in php-cgi-wasm package, using fallback path.');

            return $fallback;
        }

        // Use the first (usually only) .wasm file
        $wasmFilename = basename($wasmFiles[0]);

        return "../node_modules/php-cgi-wasm/{$wasmFilename}";
    }

    /**
     * Get the npm package versions for Laraworker dependencies.
     *
     * Centralizes version management so updates only need one place.
     *
     * @return array<string, string>
     */
    public static function npmVersions(): array
    {
        return [
            'php-cgi-wasm' => '^0.0.9-alpha-32',
            'php-wasm' => '^0.0.9-alpha-32',
            'wrangler' => '^4.0.0',
            'binaryen' => '^125.0.0',
        ];
    }

    private function detectPackageManager(): string
    {
        if (file_exists(base_path('bun.lock')) || file_exists(base_path('bun.lockb'))) {
            return 'bun';
        }
        if (file_exists(base_path('yarn.lock'))) {
            return 'yarn';
        }
        if (file_exists(base_path('pnpm-lock.yaml'))) {
            return 'pnpm';
        }

        return 'npm';
    }
}
