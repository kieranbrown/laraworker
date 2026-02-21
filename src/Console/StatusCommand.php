<?php

namespace Laraworker\Console;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class StatusCommand extends Command
{
    protected $signature = 'laraworker:status';

    protected $description = 'Show Laraworker configuration and bundle analysis';

    public function handle(): int
    {
        $this->components->info('Laraworker Status');
        $this->newLine();

        $this->showInstallationStatus();
        $this->showBundleSizes();
        $this->showTierCompatibility();
        $this->showConfiguration();
        $this->showPerformanceHints();

        return self::SUCCESS;
    }

    private function showInstallationStatus(): void
    {
        $this->components->info('Installation Status');

        $cloudflareExists = is_dir(base_path('.cloudflare'));
        $wranglerExists = file_exists(base_path('.cloudflare/wrangler.jsonc'));
        $wasmBinary = $this->detectWasmBinary();

        $this->components->bulletList([
            '.cloudflare/ directory: '.($cloudflareExists ? '<fg=green>✓</>' : '<fg=red>✗</>'),
            'wrangler.jsonc: '.($wranglerExists ? '<fg=green>✓</>' : '<fg=red>✗</>'),
            'WASM binary: '.($wasmBinary['found'] ? '<fg=green>✓</> ('.$wasmBinary['mode'].')' : '<fg=red>✗</>'),
        ]);

        $this->newLine();
    }

    private function showBundleSizes(): void
    {
        $appTarGz = base_path('.cloudflare/dist/assets/app.tar.gz');
        $wasmFiles = $this->getWasmFiles();
        $viteAssetsDir = base_path('.cloudflare/dist/assets/build');

        $this->components->info('Bundle Sizes');

        $lines = [];

        if (file_exists($appTarGz)) {
            $sizeMB = filesize($appTarGz) / 1024 / 1024;
            $lines[] = sprintf('app.tar.gz: <fg=cyan>%.2f MB</> (compressed)', $sizeMB);
        } else {
            $lines[] = 'app.tar.gz: <fg=yellow>Not built</>';
        }

        if (! empty($wasmFiles)) {
            $totalRaw = 0;
            $totalGzipped = 0;
            foreach ($wasmFiles as $file) {
                $rawSize = filesize($file);
                $gzippedSize = strlen(gzencode(file_get_contents($file), 9));
                $totalRaw += $rawSize;
                $totalGzipped += $gzippedSize;
            }
            $lines[] = sprintf(
                'WASM binary: <fg=cyan>%.2f MB</> (raw) / <fg=cyan>%.2f MB</> (gzipped estimate)',
                $totalRaw / 1024 / 1024,
                $totalGzipped / 1024 / 1024
            );
        } else {
            $lines[] = 'WASM binary: <fg=yellow>Not found</>';
        }

        if (is_dir($viteAssetsDir)) {
            $viteSize = $this->getDirectorySize($viteAssetsDir);
            $lines[] = sprintf('Vite assets: <fg=cyan>%.2f MB</>', $viteSize / 1024 / 1024);
        } else {
            $lines[] = 'Vite assets: <fg=yellow>Not found</>';
        }

        $totalSize = $this->calculateTotalWorkerSize($appTarGz, $wasmFiles);
        if ($totalSize > 0) {
            $lines[] = sprintf('Total estimated worker size: <fg=cyan>%.2f MB</> (compressed)', $totalSize / 1024 / 1024);
        }

        $this->components->bulletList($lines);
        $this->newLine();
    }

    private function showTierCompatibility(): void
    {
        $totalSize = $this->calculateTotalWorkerSize(
            base_path('.cloudflare/dist/assets/app.tar.gz'),
            $this->getWasmFiles()
        );

        $this->components->info('Tier Compatibility');

        $freeTierOk = $totalSize > 0 && $totalSize <= 3 * 1024 * 1024;
        $paidTierOk = $totalSize > 0 && $totalSize <= 10 * 1024 * 1024;

        $this->components->bulletList([
            'Free tier (3MB compressed): '.($freeTierOk ? '<fg=green>✓</>' : ($totalSize > 0 ? '<fg=red>✗</>' : '<fg=yellow>?</>')),
            'Paid tier (10MB compressed): '.($paidTierOk ? '<fg=green>✓</>' : ($totalSize > 0 ? '<fg=red>✗</>' : '<fg=yellow>?</>')),
        ]);

        $this->newLine();
    }

    private function showConfiguration(): void
    {
        $this->components->info('Configuration');

        /** @var array<string, bool> $extensions */
        $extensions = config('laraworker.extensions', []);
        $enabledExtensions = array_keys(array_filter($extensions));

        /** @var array<string> $includeDirs */
        $includeDirs = config('laraworker.include_dirs', []);

        $wasmBinary = $this->detectWasmBinary();

        $this->components->bulletList([
            'Extensions enabled: '.(empty($enabledExtensions) ? '<fg=yellow>none</>' : implode(', ', $enabledExtensions)),
            'Included dirs: '.implode(', ', $includeDirs),
            'PHP runtime mode: '.($wasmBinary['mode'] ?? '<fg=yellow>unknown</>'),
        ]);

        $this->newLine();
    }

    private function showPerformanceHints(): void
    {
        $this->components->info('Performance Hints');

        $hints = [];

        $appTarGz = base_path('.cloudflare/dist/assets/app.tar.gz');
        if (file_exists($appTarGz)) {
            $tarSize = filesize($appTarGz);
            if ($tarSize > 2 * 1024 * 1024) {
                $hints[] = '<fg=yellow>Consider stripping unused vendor packages (tar > 2MB)</>';
            }
        }

        $bootstrapCacheDir = base_path('bootstrap/cache');
        $configCached = file_exists($bootstrapCacheDir.'/config.php');
        $routesCached = file_exists($bootstrapCacheDir.'/routes-v7.php');

        if (! $configCached || ! $routesCached) {
            $hints[] = '<fg=yellow>Run php artisan laraworker:build to enable config/route caching</>';
        }

        /** @var array<string, bool> $extensions */
        $extensions = config('laraworker.extensions', []);
        if (isset($extensions['openssl']) && $extensions['openssl']) {
            $hints[] = '<fg=blue>openssl extension enabled — stubs will be skipped</>';
        }

        if (empty($hints)) {
            $hints[] = '<fg=green>No performance issues detected</>';
        }

        $this->components->bulletList($hints);
        $this->newLine();
    }

    private function detectWasmBinary(): array
    {
        $npmWasm = base_path('node_modules/php-cgi-wasm/php-cgi.wasm');
        $customWasm = base_path('.cloudflare/php-cgi.wasm');

        if (file_exists($customWasm)) {
            return ['found' => true, 'mode' => 'custom-wasm'];
        }

        if (file_exists($npmWasm)) {
            return ['found' => true, 'mode' => 'npm-package'];
        }

        return ['found' => false, 'mode' => null];
    }

    private function getWasmFiles(): array
    {
        $files = [];
        $cloudflareDir = base_path('.cloudflare');

        if (! is_dir($cloudflareDir)) {
            return $files;
        }

        $iterator = new \DirectoryIterator($cloudflareDir);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile() && $fileinfo->getExtension() === 'wasm') {
                $files[] = $fileinfo->getPathname();
            }
        }

        return $files;
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $finder = new Finder;

        try {
            $finder->files()->in($path);
            foreach ($finder as $file) {
                $size += $file->getSize();
            }
        } catch (\Exception) {
            // Directory might be empty or not readable
        }

        return $size;
    }

    private function calculateTotalWorkerSize(?string $appTarGz, array $wasmFiles): int
    {
        $total = 0;

        if ($appTarGz && file_exists($appTarGz)) {
            $total += filesize($appTarGz);
        }

        foreach ($wasmFiles as $file) {
            $content = file_get_contents($file);
            $gzipped = gzencode($content, 9);
            $total += strlen($gzipped);
        }

        return $total;
    }
}
