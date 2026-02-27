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
        'shims.ts',
        'tar.ts',
        'inertia-ssr.ts',
        'build-app.mjs',
        'tsconfig.json',
    ];

    /**
     * Extensions provided via PHP stubs (no dynamic loading needed).
     *
     * The custom PHP 8.5 WASM binary is built with MAIN_MODULE=0 (static linking).
     * All extensions are either compiled in (OPcache, session) or provided as PHP
     * stub functions (iconv, mbstring, openssl). No npm extension packages needed.
     */

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
     * Copy php.ts from the stub template.
     *
     * The custom PHP 8.5 WASM build uses static linking (MAIN_MODULE=0), so
     * no dynamic extension imports or shared library configuration is needed.
     * The stub is copied verbatim.
     */
    public function generatePhpTs(): void
    {
        $stubPath = dirname(__DIR__).'/stubs/php.ts.stub';

        copy($stubPath, $this->path('php.ts'));
    }

    /**
     * Generate worker.ts from the stub template with config-driven OPcache INI values.
     *
     * Reads opcache settings from config/laraworker.php and injects them as INI
     * directives into the worker stub, replacing the {{OPCACHE_INI}} placeholder.
     */
    public function generateWorkerTs(): void
    {
        $stubPath = dirname(__DIR__).'/stubs/worker.ts.stub';
        $content = file_get_contents($stubPath);

        /** @var array{enabled?: bool, enable_cli?: bool, memory_consumption?: int, interned_strings_buffer?: int, max_accelerated_files?: int, validate_timestamps?: bool, jit?: bool} $opcache */
        $opcache = config('laraworker.opcache', []);

        $iniLines = [];

        if ($opcache['enabled'] ?? true) {
            $iniLines[] = 'opcache.enable=1';
            $iniLines[] = 'opcache.enable_cli='.($opcache['enable_cli'] ?? true ? '1' : '0');
            $iniLines[] = 'opcache.validate_timestamps='.($opcache['validate_timestamps'] ?? false ? '1' : '0');
            $iniLines[] = 'opcache.memory_consumption='.($opcache['memory_consumption'] ?? 16);
            $iniLines[] = 'opcache.interned_strings_buffer='.($opcache['interned_strings_buffer'] ?? 4);
            $iniLines[] = 'opcache.max_accelerated_files='.($opcache['max_accelerated_files'] ?? 1000);

            if (($opcache['jit'] ?? false) === false) {
                $iniLines[] = 'opcache.jit=0';
                $iniLines[] = 'opcache.jit_buffer_size=0';
            }
        }

        $formatted = implode("\n", array_map(
            fn (string $line) => "        '{$line}',",
            $iniLines
        ));

        $content = str_replace('{{OPCACHE_INI}}', $formatted, $content);

        // Inject D1 binding lines for {{D1_BINDINGS}} placeholder
        /** @var array<int, array{binding: string, database_name: string, database_id: string}> $d1Databases */
        $d1Databases = config('laraworker.d1_databases', []);
        $d1Lines = '';

        foreach ($d1Databases as $db) {
            $binding = $db['binding'];
            $d1Lines .= "    if (env.{$binding}) cfd1['{$binding}'] = env.{$binding};\n";
        }

        $content = str_replace('{{D1_BINDINGS}}', rtrim($d1Lines), $content);

        // Inject SSR import when Inertia SSR is enabled
        $ssrImport = '';
        if (config('laraworker.inertia.ssr')) {
            $ssrImport = "import _ssrRender from './ssr/ssr';\nssrRender = _ssrRender;";
        }
        $content = str_replace('{{SSR_IMPORT}}', $ssrImport, $content);

        file_put_contents($this->path('worker.ts'), $content);
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

        $config = json_decode($content, true);

        // Inject account_id when configured
        $accountId = config('laraworker.account_id');
        if ($accountId) {
            $config['account_id'] = $accountId;
        }

        /** @var array<int, array{pattern: string, custom_domain?: bool}> $routes */
        $routes = config('laraworker.routes', []);

        if (! empty($routes)) {
            $config['routes'] = $routes;
        }

        // Inject worker environment variables for Inertia SSR
        if (config('laraworker.inertia.ssr')) {
            $config['vars'] ??= [];
            $config['vars']['INERTIA_SSR'] = 'true';
        }

        // Inject D1 database bindings into wrangler config
        /** @var array<int, array{binding: string, database_name: string, database_id: string}> $d1Databases */
        $d1Databases = config('laraworker.d1_databases', []);

        if (! empty($d1Databases)) {
            $config['d1_databases'] = array_map(fn (array $db) => [
                'binding' => $db['binding'],
                'database_name' => $db['database_name'],
                'database_id' => $db['database_id'],
                'migrations_dir' => 'migrations',
            ], $d1Databases);
        }

        $content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

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
        $overrides = config('laraworker.env_overrides', []);

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
            'public_assets' => config('laraworker.public_assets', true),
            'locales' => config('laraworker.locales', ['en']),
            'memfs_budget_mb' => config('laraworker.memfs_budget_mb', 30),
            'show_top_dirs' => config('laraworker.show_top_dirs', false),
        ];

        file_put_contents(
            $this->path('build-config.json'),
            json_encode($config, JSON_PRETTY_PRINT)."\n"
        );
    }

    /**
     * Copy the custom PHP 8.5 WASM binary and helper modules into the build directory.
     *
     * The php-wasm-build/ directory in the package root contains the output of
     * `bash php-wasm-build/build.sh`: the Emscripten JS module, WASM binary,
     * and PhpCgiBase helper files. These are copied into the build directory
     * so build-app.mjs can find them at runtime.
     */
    public function copyWasmBinary(): void
    {
        $this->ensureDirectory();

        $wasmBuildDir = dirname(__DIR__).'/php-wasm-build';
        $buildDir = $this->path();

        // Copy Emscripten JS module → php-cgi.mjs (with Workers compatibility patch)
        $moduleSrc = $wasmBuildDir.'/php8.5-cgi-worker.mjs';
        if (! file_exists($moduleSrc)) {
            throw new \RuntimeException("Custom PHP module not found at {$moduleSrc}. Run: bash php-wasm-build/build.sh");
        }

        $moduleContent = file_get_contents($moduleSrc);

        // Patch: Replace `new URL("...wasm", import.meta.url).href` with try/catch fallback
        $moduleContent = preg_replace(
            '/new URL\("([^"]+\.wasm)",\s*import\.meta\.url\)\.href/',
            '(() => { try { return new URL("$1", import.meta.url).href; } catch { return "$1"; } })()',
            $moduleContent
        );

        file_put_contents($buildDir.'/php-cgi.mjs', $moduleContent);

        // Copy WASM binary
        $wasmFiles = glob($wasmBuildDir.'/*.wasm');
        if (empty($wasmFiles)) {
            throw new \RuntimeException("No .wasm file found in {$wasmBuildDir}. Run: bash php-wasm-build/build.sh");
        }
        copy($wasmFiles[0], $buildDir.'/php-cgi.wasm');

        // Copy helper modules that PhpCgiBase.mjs depends on
        $helpers = ['PhpCgiBase.mjs', 'breakoutRequest.mjs', 'parseResponse.mjs', 'fsOps.mjs', 'resolveDependencies.mjs'];
        foreach ($helpers as $helper) {
            $src = $wasmBuildDir.'/'.$helper;
            if (file_exists($src)) {
                copy($src, $buildDir.'/'.$helper);
            }
        }
    }

    /**
     * Resolve the WASM import path for the custom PHP 8.5 binary.
     *
     * The build script (build-app.mjs) copies the custom binary from
     * php-wasm-build/ into the build directory as php-cgi.wasm.
     */
    public function resolvePhpWasmImport(): string
    {
        return './php-cgi.wasm';
    }
}
