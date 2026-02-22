# Drastically Improve Cold/Warm Request Performance

## Goal
Significantly reduce cold start (~2s) and warm request times for Laravel on Cloudflare Workers. Current bottleneck is PHP WASM execution parsing all framework files without OPcache.

## Current Benchmarks (Feb 2026, laraworker.kswb.dev)

| Metric | Value |
|--------|-------|
| Cold start TTFB | ~2.4–2.8s |
| Warm TTFB (fast) | ~0.43–0.67s |
| Warm TTFB (slow) | ~1.6–1.9s |
| Bundle size (all ext) | ~4.5–5.0 MB |

See `.fuel/docs/performance-investigation.md` for full breakdown.

## Investigation Results (Completed)

### 1. Tar extraction — NOT the bottleneck (~30–80ms of ~2.5s cold start)
- ✅ Investigated. Tar unpack is fast. Static Assets can't replace MEMFS mounting.
- KV/R2 caching not worth the complexity for ~50ms savings.
- **Keep current tar approach.**

### 2. PHP file parsing — HIGH IMPACT opportunity
- ~50–150 PHP files loaded per request even with caching
- OPcache not available in Emscripten-based php-cgi-wasm
- **ClassPreloader** can bundle all classes into a single file → est. 100–300ms warm savings
- **php_strip_whitespace** at build time → est. 20–60ms savings, 10–20% smaller tar
- Removing unused service providers → est. 5–15ms savings

### 3. WASM caching — already correctly implemented, platform-blocked for more
- ✅ Module-level `php` instance persists across warm requests (correct pattern)
- No V8 WASM code caching in workerd (Cloudflare must implement)
- WASM memory snapshots (like Python Workers) would be transformative but blocked
- Cloudflare's Shard & Conquer achieves 99.99% warm rate automatically

### 4. Bundle size — addressable via extension config
- Extensions add ~1.7 MB (mbstring 742 KiB + openssl 936 KiB)
- Making extensions optional gets bundle to ~3.0–3.5 MB (near free tier)
- Tree-shaking Illuminate is high-effort, high-risk

### 5. Cloudflare platform — limited additional gains
- Static Assets already correctly used
- Smart Placement not useful (no external backend)
- 10ms CPU free tier is insufficient; paid tier required
- 128 MB memory limit is a constraint to monitor

## Recommended Implementation Order

### Phase 1 — Build optimizations (no runtime changes)
1. Add `php -w` strip whitespace to build-app.mjs for all PHP files in tar
2. Make WASM extensions configurable with sensible defaults
3. Add build-time vendor pruning (CLI scripts, unused Symfony resources)
4. Document Workers-optimized service provider set in config

### Phase 2 — ClassPreloader integration
5. Integrate ClassPreloader into build pipeline
6. Generate single preload file with all framework + vendor classes
7. Benchmark before/after

### Phase 3 — Advanced (platform-dependent)
8. Monitor CF WASM code caching / memory snapshot progress
9. OPcache patching (WordPress Playground approach) if Phase 2 insufficient
10. Illuminate tree-shaker as last resort

## Key Decisions & Patterns
- Tar extraction is NOT worth replacing — focus on PHP-level optimizations
- ClassPreloader is the highest-impact addressable optimization for warm requests
- Cold start <1s is NOT achievable without Cloudflare platform changes (WASM compilation alone is ~500–800ms)
- Warm request <100ms is aggressive but approachable with ClassPreloader + strip whitespace

## Gotchas
- Free tier 10ms CPU limit is insufficient for PHP-on-WASM — document this prominently
- 128 MB isolate memory shared between JS heap and WASM linear memory
- 1 second startup time limit is tight with full extension set
- Laravel's internal dependencies are deeply intertwined — tree-shaking is risky
- PHAR bundling is counterproductive (slower loading than direct includes)

## Success Criteria (Revised Assessment)
- Cold start < 1s — ❌ Blocked by platform (WASM compilation ~500–800ms alone)
- Warm request < 100ms — ⚠️ Aggressive but approachable with Phase 1+2
- Bundle stays within 3 MiB free tier — ✅ Achievable with optional extensions

## Dependency
Blocked by e-5f9289 (local testing playground) — need to be able to benchmark locally before and after changes.