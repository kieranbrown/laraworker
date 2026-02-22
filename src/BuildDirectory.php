<?php

namespace Laraworker;

use Illuminate\Support\Str;

class BuildDirectory
{
    /**
     * Build directory name relative to the project root.
     */
    public const DIRECTORY = '.laraworker';

    /**
     * Static stub files copied verbatim from the package stubs/ directory.
     */
    private const STUBS = [
        'worker.ts',
        'shims.ts',
        'tar.ts',
        'inertia-ssr.ts',
        'build-app.mjs',
        'tsconfig.json',
    ];

    /** @var array<string, array{imports: string, shared_libs: string[], preloaded_libs: array<string, string>, npm_packages: array<string, string>}> */
    public const EXTENSION_REGISTRY = [
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

    /**
     * Return the absolute path to the build directory, optionally appending a relative path.
     */
    public function path(string $relative = ''): string
    {
        $base = base_path(self::DIRECTORY);

        if ($relative === '') {
            return $base;
        }

        return $base.'/'.ltrim($relative, '/');
    }

    /**
     * Create the build directory if it doesn't exist.
     */
    public function ensureDirectory(): void
    {
        $dir = $this->path();

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Copy all static stub files from the package's stubs/ directory into the build directory.
     */
    public function copyStubs(): void
    {
        $this->ensureDirectory();

        $stubDir = dirname(__DIR__).'/stubs';

        foreach (self::STUBS as $stub) {
            copy($stubDir.'/'.$stub, $this->path($stub));
        }
    }

    /**
     * Generate php.ts from the stub template using the extension registry.
     *
     * @param  array<string, bool>  $extensionRegistry  Enabled extensions map (e.g. ['mbstring' => true])
     */
    public function generatePhpTs(array $extensionRegistry): void
    {
        $enabledExtensions = array_keys(array_filter($extensionRegistry));

        $stubPath = dirname(__DIR__).'/stubs/php.ts.stub';
        $content = file_get_contents($stubPath);

        $imports = [];
        $sharedLibs = [];
        $preloadedLibs = [];

        foreach ($enabledExtensions as $ext) {
            if (! isset(self::EXTENSION_REGISTRY[$ext])) {
                continue;
            }

            $reg = self::EXTENSION_REGISTRY[$ext];
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

        file_put_contents($this->path('php.ts'), $content);
    }

    /**
     * Generate wrangler.jsonc from the stub template using laraworker config values.
     */
    public function generateWranglerConfig(): void
    {
        $stubPath = dirname(__DIR__).'/stubs/wrangler.jsonc.stub';
        $content = file_get_contents($stubPath);

        $workerName = config('laraworker.worker_name')
            ?? Str::slug(config('app.name', 'laravel'));

        $compatibilityDate = config('laraworker.compatibility_date')
            ?? now()->format('Y-m-d');

        $content = str_replace('{{APP_NAME}}', $workerName, $content);
        $content = str_replace('{{COMPATIBILITY_DATE}}', $compatibilityDate, $content);

        file_put_contents($this->path('wrangler.jsonc'), $content);
    }

    /**
     * Generate .env.production from the base .env file with config-driven overrides.
     */
    public function generateEnvProduction(): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $env = file_get_contents($envPath);

        /** @var array<string, string> $overrides */
        $overrides = config('laraworker.env_overrides', [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'LOG_CHANNEL' => 'stderr',
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
        ]);

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

        // Append any overrides not already present in .env
        foreach ($overrides as $key => $value) {
            if (! isset($seen[$key])) {
                $result[] = "{$key}={$value}";
            }
        }

        file_put_contents($this->path('.env.production'), implode("\n", $result));
    }

    /**
     * Write build-config.json from the laraworker config values.
     */
    public function writeBuildConfig(): void
    {
        $config = [
            'extensions' => config('laraworker.extensions', []),
            'include_dirs' => config('laraworker.include_dirs', []),
            'include_files' => config('laraworker.include_files', []),
            'exclude_patterns' => config('laraworker.exclude_patterns', []),
            'strip_whitespace' => config('laraworker.strip_whitespace', false),
            'strip_providers' => config('laraworker.strip_providers', []),
        ];

        file_put_contents(
            $this->path('build-config.json'),
            json_encode($config, JSON_PRETTY_PRINT)."\n"
        );
    }

    /**
     * Resolve the WASM import path for php-cgi-wasm.
     *
     * Globs node_modules/php-cgi-wasm/*.wasm to find the actual filename
     * instead of hardcoding a hash that breaks on package updates.
     */
    public function resolvePhpWasmImport(): string
    {
        $wasmDir = base_path('node_modules/php-cgi-wasm');
        $fallback = '../node_modules/php-cgi-wasm/php-cgi.wasm';

        if (! is_dir($wasmDir)) {
            return $fallback;
        }

        $wasmFiles = glob($wasmDir.'/*.wasm');

        if (empty($wasmFiles)) {
            return $fallback;
        }

        $wasmFilename = basename($wasmFiles[0]);

        return "../node_modules/php-cgi-wasm/{$wasmFilename}";
    }
}
