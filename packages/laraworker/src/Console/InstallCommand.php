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
        $this->publishStubs();
        $this->generatePhpTs();
        $this->generateWranglerConfig();
        $this->generateEnvProduction();
        $this->updatePackageJson();
        $this->updateGitignore();
        $this->installNpmDependencies();
        $this->runInitialBuild();

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

            $stubs = ['worker.ts', 'shims.ts', 'tar.ts', 'build-app.mjs', 'tsconfig.json'];

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

            $content = str_replace('{{EXTENSIONS_COMMENT}}', $extensionsComment, $content);
            $content = str_replace('{{EXTENSION_IMPORTS}}', $importsBlock, $content);
            $content = str_replace('{{SHARED_LIBS}}', $sharedLibsStr, $content);
            $content = str_replace('{{PRELOADED_LIBS}}', $preloadedLibsBlock, $content);

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

            // Add base dependencies
            $packageJson['devDependencies'] = array_merge($packageJson['devDependencies'] ?? [], [
                'php-cgi-wasm' => '^0.0.9-alpha-32',
                'php-wasm' => '^0.0.9-alpha-32',
                'wrangler' => '^4.66.0',
                'binaryen' => '^125.0.0',
            ]);

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
                'bun' => 'bun install',
                'yarn' => 'yarn install',
                'pnpm' => 'pnpm install',
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

            exec('node '.base_path('.cloudflare/build-app.mjs').' 2>&1', $output, $exitCode);

            if ($exitCode !== 0) {
                $this->components->warn('Initial build failed. Run "php artisan laraworker:build" manually.');
                foreach ($output as $line) {
                    $this->line("  {$line}");
                }
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
