<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Laraworker\BuildDirectory;

class InstallCommand extends Command
{
    protected $signature = 'laraworker:install
        {--force : Overwrite existing files}';

    protected $description = 'Install Laraworker scaffolding for Cloudflare Workers';

    public function handle(): int
    {
        $this->components->info('Installing Laraworker...');

        $this->detectLegacyDirectory();

        $this->publishConfig();
        $this->updatePackageJson();
        $this->updateGitignore();
        $this->installNpmDependencies();
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

    /**
     * Warn users about a legacy .cloudflare/ directory from a previous installation.
     */
    private function detectLegacyDirectory(): void
    {
        if (is_dir(base_path('.cloudflare'))) {
            $this->components->warn(
                'Detected .cloudflare/ directory from a previous installation. '
                .'You can safely delete it — all build files are now generated in .laraworker/ at build time.'
            );
        }
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

            $entry = '/'.BuildDirectory::DIRECTORY.'/';

            if (! str_contains($gitignore, $entry)) {
                $gitignore = rtrim($gitignore)."\n\n# Laraworker\n".$entry."\n";
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
        $this->components->task('Running initial build', function () {
            $exitCode = $this->call('laraworker:build');

            if ($exitCode !== self::SUCCESS) {
                $this->components->warn('Initial build failed. Run "php artisan laraworker:build" manually.');
            }
        });
    }

    private function verifyInstallation(): void
    {
        $this->newLine();
        $this->components->info('Verifying installation...');

        $allPassed = true;

        // Config file must exist
        $configExists = file_exists(config_path('laraworker.php'));
        $status = $configExists ? '<info>✓</info>' : '<error>✗</error>';
        $this->line("  {$status} config/laraworker.php");
        if (! $configExists) {
            $allPassed = false;
        }

        // Build directory should have been created by the build
        $buildDirExists = is_dir(base_path(BuildDirectory::DIRECTORY));
        $status = $buildDirExists ? '<info>✓</info>' : '<error>✗</error>';
        $this->line("  {$status} ".BuildDirectory::DIRECTORY.'/');
        if (! $buildDirExists) {
            $allPassed = false;
        }

        if (! $allPassed) {
            $this->components->warn('Some checks failed. Run "php artisan laraworker:build" to regenerate build files.');
        }
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
