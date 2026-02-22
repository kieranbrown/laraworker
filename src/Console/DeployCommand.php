<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DeployCommand extends Command
{
    protected $signature = 'laraworker:deploy
                            {--dry-run : Build but do not deploy}';

    protected $description = 'Build and deploy the Laravel application to Cloudflare Workers';

    private const FREE_TIER_LIMIT = 3 * 1024 * 1024; // 3MB

    private const PAID_TIER_LIMIT = 10 * 1024 * 1024; // 10MB

    public function handle(): int
    {
        if (! is_dir(base_path('.cloudflare'))) {
            $this->components->error('Laraworker not installed. Run: php artisan laraworker:install');

            return self::FAILURE;
        }

        // Pre-flight checks
        if (! $this->runPreflightChecks()) {
            return self::FAILURE;
        }

        // Run build first
        $buildExitCode = $this->call('laraworker:build');
        if ($buildExitCode !== self::SUCCESS) {
            return self::FAILURE;
        }

        // Check app.tar.gz exists after build
        if (! File::exists(base_path('.cloudflare/dist/assets/app.tar.gz'))) {
            $this->components->error('Build failed: app.tar.gz not found.');

            return self::FAILURE;
        }

        // Check bundle size
        $bundleSize = $this->getBundleSize();
        $this->displayBundleSizeInfo($bundleSize);

        if ($this->option('dry-run')) {
            $this->components->info('Dry run complete. No deployment performed.');
            $this->displayDryRunInfo($bundleSize);

            return self::SUCCESS;
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

        $output = '';
        $process->run(function (string $type, string $buffer) use (&$output): void {
            $output .= $buffer;
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->components->error('Deployment failed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Deployed successfully!');

        // Parse and display deployment info
        $this->displayPostDeployInfo($output, $bundleSize);

        return self::SUCCESS;
    }

    private function runPreflightChecks(): bool
    {
        $this->components->info('Running pre-flight checks...');
        $passed = true;

        // Check wrangler.jsonc exists and has account_id
        $wranglerConfigPath = base_path('.cloudflare/wrangler.jsonc');
        if (! File::exists($wranglerConfigPath)) {
            $this->components->warn('  wrangler.jsonc not found.');
            $passed = false;
        } else {
            $config = $this->parseWranglerConfig($wranglerConfigPath);
            if (empty($config['account_id'])) {
                $this->components->warn('  wrangler.jsonc: account_id is not set. Deployment may fail.');
            } else {
                $this->components->info('  wrangler.jsonc: account_id configured.');
            }
        }

        // Check wrangler auth
        if (! $this->checkWranglerAuth()) {
            $this->components->warn('  Wrangler not authenticated. Run: npx wrangler login');
            $passed = false;
        } else {
            $this->components->info('  Wrangler authentication: OK');
        }

        $this->newLine();

        return $passed;
    }

    private function parseWranglerConfig(string $path): array
    {
        $content = File::get($path);

        // Remove comments (both // and /* */)
        $content = preg_replace('/\/\/.*$/m', '', $content);
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);

        return json_decode($content, true) ?? [];
    }

    private function checkWranglerAuth(): bool
    {
        $process = new Process(
            ['npx', 'wrangler', 'whoami'],
            base_path('.cloudflare'),
            null,
            null,
            30
        );

        $process->run();

        return $process->isSuccessful();
    }

    private function getBundleSize(): array
    {
        $cloudflarePath = base_path('.cloudflare');
        $workerSize = 0;
        $assetsSize = 0;
        $assetCount = 0;

        // Get worker size (main entry point and any built JS)
        $workerFiles = ['worker.ts', 'worker.js', 'index.js'];
        foreach ($workerFiles as $file) {
            $path = $cloudflarePath.'/'.$file;
            if (File::exists($path)) {
                $workerSize += File::size($path);
            }
        }

        // Check dist directory for assets
        $distPath = $cloudflarePath.'/dist';
        if (is_dir($distPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($distPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size = $file->getSize();
                    if ($file->getFilename() === 'app.tar.gz') {
                        $workerSize += $size;
                    } else {
                        $assetsSize += $size;
                        $assetCount++;
                    }
                }
            }
        }

        return [
            'worker' => $workerSize,
            'assets' => $assetsSize,
            'total' => $workerSize + $assetsSize,
            'asset_count' => $assetCount,
        ];
    }

    private function displayBundleSizeInfo(array $bundleSize): void
    {
        $this->components->info('Bundle size estimate:');
        $this->line(sprintf('  Worker size: %s', $this->formatBytes($bundleSize['worker'])));
        $this->line(sprintf('  Static assets: %s (%d files)', $this->formatBytes($bundleSize['assets']), $bundleSize['asset_count']));
        $this->line(sprintf('  Total: %s', $this->formatBytes($bundleSize['total'])));

        if ($bundleSize['total'] > self::PAID_TIER_LIMIT) {
            $this->components->error(sprintf(
                '  Bundle exceeds paid tier limit (%s)',
                $this->formatBytes(self::PAID_TIER_LIMIT)
            ));
        } elseif ($bundleSize['total'] > self::FREE_TIER_LIMIT) {
            $this->components->warn(sprintf(
                '  Bundle exceeds free tier limit (%s). Paid tier required.',
                $this->formatBytes(self::FREE_TIER_LIMIT)
            ));
        } else {
            $this->components->info(sprintf(
                '  Within free tier limit (%s)',
                $this->formatBytes(self::FREE_TIER_LIMIT)
            ));
        }

        $this->newLine();
    }

    private function displayDryRunInfo(array $bundleSize): void
    {
        $this->newLine();
        $this->components->info('Files that would be deployed:');

        $cloudflarePath = base_path('.cloudflare');
        $distPath = $cloudflarePath.'/dist';

        if (is_dir($distPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($distPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($distPath.'/', '', $file->getPathname());
                    $this->line(sprintf('  %s (%s)', $relativePath, $this->formatBytes($file->getSize())));
                }
            }
        }

        // List worker files
        $workerFiles = ['worker.ts', 'worker.js', 'index.js', 'wrangler.jsonc'];
        foreach ($workerFiles as $file) {
            $path = $cloudflarePath.'/'.$file;
            if (File::exists($path)) {
                $this->line(sprintf('  %s (%s)', $file, $this->formatBytes(File::size($path))));
            }
        }
    }

    private function displayPostDeployInfo(string $output, array $bundleSize): void
    {
        $this->newLine();
        $this->components->info('Deployment Summary');

        // Parse deployment URL from wrangler output
        $url = $this->parseDeploymentUrl($output);
        if ($url) {
            $this->line(sprintf('  Deployed to: %s', $url));
        }

        // Check for custom domain in wrangler config
        $customDomain = $this->getCustomDomain();
        if ($customDomain) {
            $this->line(sprintf('  Custom domain: https://%s', $customDomain));
        }

        // Display stats
        $this->newLine();
        $this->line(sprintf('  Bundle size: %s', $this->formatBytes($bundleSize['total'])));
        $this->line(sprintf('  Assets uploaded: %d', $bundleSize['asset_count']));
    }

    private function parseDeploymentUrl(string $output): ?string
    {
        // Wrangler deploy output typically contains:
        // "Deployed <worker-name> to <url>"
        // or "Published <worker-name> (<url>)"
        if (preg_match('/(?:Deployed|Published)\s+\S+\s+(?:to\s+)?([^\s\)]+)/i', $output, $matches)) {
            $url = $matches[1];

            // Clean up the URL
            $url = trim($url, '()');

            return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
        }

        return null;
    }

    private function getCustomDomain(): ?string
    {
        $wranglerConfigPath = base_path('.cloudflare/wrangler.jsonc');
        if (! File::exists($wranglerConfigPath)) {
            return null;
        }

        $config = $this->parseWranglerConfig($wranglerConfigPath);

        // Check for routes with custom domains
        if (! empty($config['routes'])) {
            foreach ($config['routes'] as $route) {
                if (is_string($route) && ! str_contains($route, '*')) {
                    return $route;
                }
                if (is_array($route) && ! empty($route['pattern']) && ! str_contains($route['pattern'], '*')) {
                    return $route['pattern'];
                }
            }
        }

        // Check for workers.dev subdomain override
        if (! empty($config['workers_dev']) && $config['workers_dev'] === false) {
            // Custom domain setup
            if (! empty($config['routes'])) {
                $route = $config['routes'][0];

                return is_string($route) ? $route : ($route['pattern'] ?? null);
            }
        }

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $bytes, $units[$unitIndex]);
    }
}
