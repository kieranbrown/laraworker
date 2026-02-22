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