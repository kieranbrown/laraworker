# Memory Management

## OPcache in WASM

### Overview

OPcache caches compiled PHP opcodes in WASM linear memory, providing significant performance improvements for warm requests on Cloudflare Workers.

### How It Works

In the Cloudflare Workers environment:

1. **Cold start**: PHP files are parsed and compiled to opcodes, stored in WASM linear memory
2. **Warm requests**: The same isolate reuses cached opcodes from previous requests
3. **Isolate eviction**: When Cloudflare evicts an isolate, the cache is lost (next request is cold)

Unlike traditional PHP, OPcache in WASM stores opcodes in Emscripten's MEMFS-backed memory rather than System V shared memory. This works because:

- WASM linear memory persists across requests within a warm isolate
- Files never change (MEMFS is read-only after tar extraction)
- No `dlopen/dlsym` required (OPcache is statically linked)

### Expected Performance

Based on WordPress Playground benchmarks (July 2025):

| Metric | Without OPcache | With OPcache | Improvement |
|--------|----------------|--------------|-------------|
| Warm request time | ~400-650ms | ~130-220ms | ~3x faster |
| Cold start impact | Baseline | +0ms | No penalty |
| Memory usage | Baseline | +32MB (configurable) | Cache storage |

### Configuration

OPcache is configured in `config/laraworker.php`:

```php
'opcache' => [
    'enabled' => true,
    'enable_cli' => true,
    'memory_consumption' => 32,        // MB - tune for your app
    'max_accelerated_files' => 1000,   // Laravel needs ~500-800
    'validate_timestamps' => false,      // Files never change in MEMFS
    'jit' => false,                     // JIT provides no benefit in WASM
],
```

### Tuning Guidelines

**Memory Consumption**
- Laravel 12 default app: 32MB sufficient
- Apps with many dependencies: 64MB recommended
- Monitor with `opcache_get_status()` to check hit rate

**Max Accelerated Files**
- Laravel framework: ~500-600 files
- With vendor dependencies: ~800-1000 files
- Set to power of 2 minus 1 for hash table efficiency

**Timestamp Validation**
- Always `false` in production — files never change
- Workers environment uses MEMFS, not persistent storage

### OPcache + ClassPreloader

OPcache and ClassPreloader are complementary optimizations:

| Optimization | Solves | Benefit |
|-------------|--------|---------|
| ClassPreloader | Autoloader filesystem lookups | Eliminates 50-100 MEMFS `analyzePath()` calls per request |
| OPcache | PHP file parsing | Eliminates re-parsing of compiled opcodes on warm isolates |

**Load order matters** — ClassPreloader should load before OPcache warms:

1. `auto_prepend_file=/app/bootstrap/preload.php` (ClassPreloader)
2. First request parses and caches opcodes
3. Subsequent warm requests use cached opcodes

### Monitoring

Enable OPcache status endpoint in your app to monitor:

```php
Route::get('/opcache-status', function () {
    return opcache_get_status();
});
```

Key metrics to watch:
- `opcache_hit_rate` — should be >90% after warmup
- `cache_full` — indicates memory consumption too low
- `num_cached_scripts` — compare to `max_accelerated_files`

### Limitations

1. **Cold starts pay full cost**: First request to a new isolate compiles everything
2. **128MB isolate limit**: OPcache memory counts against total isolate budget
3. **No JIT benefit**: JIT is disabled — WASM execution model differs from native
4. **Platform dependency**: Requires OPcache to be statically linked in PHP WASM binary

### Binary Size Impact

| Component | Size Impact |
|-----------|-------------|
| OPcache extension | ~100-150 KB gzipped |
| Runtime memory allocation | +32-64 MB (configurable) |
| Total bundle impact | Minimal — worth the tradeoff |

### See Also

- `config/laraworker.php` — OPcache configuration
- `.fuel/docs/performance-investigation.md` — Full performance analysis
- WordPress Playground issue #2251 — Prior art
