# Drastically Improve Cold/Warm Request Performance

## Goal
Significantly reduce cold start (~2s) and warm request times for Laravel on Cloudflare Workers. Current bottleneck is PHP WASM execution parsing all framework files without OPcache.

## Investigation Areas

### 1. Eliminate custom tar logic
- Is our custom tar archive + MEMFS unpack still the best approach?
- Cloudflare Workers now has **Workers Assets** — can we serve static files via assets binding and only WASM-process PHP routes?
- Cloudflare has **Workers KV**, **R2**, and **Durable Objects** — could pre-built filesystem snapshots be cached?
- Investigate if Cloudflare's **Static Assets** can serve the entire app directory, avoiding tar extraction entirely

### 2. Tree shaking / dead code elimination
- Laravel bootstraps many service providers we may not need in a WASM context
- Can we strip unused service providers, facades, and middleware?
- Investigate `composer dump-autoload --classmap-authoritative` effectiveness
- Can we precompute the autoloader to only include classes actually used in routes?
- PHP file-level tree shaking: only include files that are actually `require`d

### 3. PHP WASM execution optimization
- OPcache equivalent for WASM? Pre-compile PHP to opcodes at build time
- Investigate PHP preloading (`opcache.preload`) — can we bake preloaded files into the WASM memory?
- Reduce number of PHP files parsed on each request (current: parses entire framework)
- Single-file bundler: concatenate all PHP files into fewer files to reduce filesystem overhead

### 4. Worker-level optimizations
- Module-level caching: can the WASM module persist between requests in the same isolate?
- Lazy initialization: defer service provider boots until actually needed
- Strip all comments/whitespace from PHP files at build time (php-strip-whitespace)

### 5. Bundle size reduction (affects cold start)
- Current: 2,596 KiB gzipped WASM + 3.35 MB compressed tar
- Audit which PHP extensions are truly needed
- Consider lighter alternatives to full Laravel (e.g., stripped framework bootstrap)
- Compress vendor more aggressively — are there large unused files?

## Success Criteria
- Cold start < 1s
- Warm request < 100ms
- Bundle stays within Cloudflare free tier (3 MiB worker, 25 MiB assets)

## Dependency
Blocked by e-5f9289 (local testing playground) — need to be able to benchmark locally before and after changes.

---

## Implemented Optimizations (f-cb3f9c)

### 1. PHP Whitespace Stripping at Build Time [DONE]
- **File**: `stubs/build-app.mjs` — `stripPhpFile()` function, integrated into `createTar()`
- **Config**: `config/laraworker.php` → `strip_whitespace` (default: `true`)
- **How**: Runs `php -w` on every `.php` file before packing into tar
- **Impact**: ~30-40% reduction per PHP file, reduces parse time in WASM
- **Passed to build-app.mjs via**: `build-config.json` → `strip_whitespace`

### 2. Additional Vendor File Pruning [DONE]
- **File**: `stubs/build-app.mjs` — `DEFAULT_EXCLUDE_PATTERNS` array
- **New patterns**: vendor/bin/, vendor/*/bin/, Symfony translations, Illuminate/Testing/, Foundation/Console/stubs/, .github/, .travis.yml, CONTRIBUTING, SECURITY.md
- **Impact**: Removes CLI scripts, locale files, testing utilities, dev files

### 3. Service Provider Stripping [DONE]
- **File**: `src/Console/BuildCommand.php` → `stripServiceProviders()` method
- **Config**: `config/laraworker.php` → `strip_providers` array
- **How**: After config:cache, removes listed providers from cached config file using regex replacement of double-backslash escaped class names
- **Default stripped**: BroadcastServiceProvider, BusServiceProvider, NotificationServiceProvider
- **Gotcha**: Cached config uses double backslashes in class names (`Illuminate\\Bus\\BusServiceProvider`), so regex must handle this via `str_replace('\\', '\\\\', $provider)` before `preg_quote()`

### 4. Class Preloader [DONE]
- **File**: `src/Console/BuildCommand.php` → `generatePreloadFile()` method
- **Output**: `bootstrap/preload.php` (auto-cleaned up after build)
- **How**: Reads `vendor/composer/autoload_classmap.php`, filters to core Illuminate namespaces (Support, Routing, Http, Foundation, Container, Pipeline, Config, Events, View, etc.), excludes Console/Testing namespaces, generates `require_once` list with `/app/` WASM paths
- **Loaded via**: `php-stubs.php` (auto_prepend_file) — conditionally includes `/app/bootstrap/preload.php`
- **Impact**: Eliminates per-class autoloader lookups and MEMFS analyzePath() overhead for ~50-150 framework files

### Key Decisions
- **require_once approach** (not concatenation): Simpler, more robust, avoids complex namespace/dependency resolution. Still saves autoloader lookup + classmap hash table overhead per class
- **Filtered namespaces**: Only core web-request namespaces preloaded (not Database, Queue, Mail, Console, etc.) to avoid loading unused code
- **Configurable stripping**: Both whitespace and providers are configurable so users can disable if needed
- **Stubs integration**: Preloader loaded via existing auto_prepend_file mechanism (php-stubs.php)

### Patterns for Future Agents
- Build config flows: `config/laraworker.php` → `BuildCommand::writeBuildConfig()` → `.cloudflare/build-config.json` → `build-app.mjs`
- Runtime files generated during build should be cleaned up in `restoreLocalEnvironment()`
- Tests use Orchestra Testbench — `base_path()` points to testbench's Laravel skeleton, not the package root. Fake classmaps/files needed for testing build features

### Remaining Optimization Opportunities
- **Concatenated class preloader** (v2): Merge all class files into single PHP file for true single-parse-pass benefit. Requires topological sort of class dependencies
- **Configurable WASM extensions**: Already supported in config — users can disable mbstring/openssl to save ~1.7MB
- **Illuminate component tree-shaking**: Aggressive removal of unused framework components (high effort, high risk)
- **OPcache in WASM**: Following WordPress Playground's approach (very high effort)