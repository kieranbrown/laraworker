# Epic: PHP 8.5 + OPcache Integration and Wrangler Hardening

## Goal
Get laraworker running PHP 8.5 with OPcache on Cloudflare Workers. Fix wrangler config issues introduced by a previous agent's reverts. Loop until https://laraworker.kswb.dev returns HTTP 200.

## CRITICAL SAFETY RULES
- **NEVER** delete the git repository or working directory
- **NEVER** touch `.fuel/agent.db` or `.fuel/config.yml`
- **NEVER** run `rm -rf` on the project root or any parent directory
- **NEVER** run `git clean -fdx` at the project root
- Always commit changes incrementally so they can be reverted

## Context

### Current State
- The npm `php-cgi-wasm` package provides PHP 8.3 (file: `a6b86cfb60faf7f8d8e7143644223328da17002c.wasm`)
- `php-wasm-build/` has scripts to build a custom PHP 8.5 WASM with OPcache, but **it is NOT integrated** into the worker
- `stubs/php.ts.stub` imports WASM from the npm package, not the custom build
- Extension npm packages (`php-wasm-mbstring`, `php-wasm-openssl`) ship PHP 8.3 `.so` files — these are ABI incompatible with PHP 8.5
- The custom build config (`.php-wasm-rc`) has `WITH_MBSTRING=0`, `WITH_OPENSSL=0` — extensions are NOT in the custom binary
- A previous agent reverted observability and nodejs_compat changes (commit `5f60259`)
- The previous agent also added debugging endpoints (`__health`, `__diag`) that are now stale (commit `78fa956` removed `__diag` but `__health` remains — this is fine to keep)
- OPcache INI settings were removed from worker.ts (commit `5abc381`)

### What worked before
The site at https://laraworker.kswb.dev WAS working with the npm PHP 8.3 binary before the recent agent changes. The agent's reverts and diagnostic commits may have broken the deployment.

### Reference: OPcache in PHP WASM
WordPress Playground enabled OPcache in PHP WASM (July 2025) with ~3x speedup (620ms → 205ms).
Blog: https://wasmer.io/posts/running-php-blazingly-fast-at-the-edge-with-wasm
The approach uses static linking and patched mmap(MAP_ANON) detection — same approach used in `php-wasm-build/patches/opcache-wasm-support.sh`.

## Tasks (in order)

### Task 1: Fix wrangler.jsonc.stub — observability, remove nodejs_compat, keep minify
- [x] Completed (iteration 1, commit 70977ef)

### Task 2: Add OPcache INI settings to worker.ts
- [x] Completed (iteration 2, commit 509ad16)

### Task 3: Clean up php8.3 references — make them version-aware
- [x] Completed (iteration 3, commit 7d08df0)

### Task 4: Push changes and verify CI passes
- [x] Merged to main and pushed (iteration 4)
- [x] CI passed (run 22330854155, 6m42s)
- [x] Fixed addEventListener useCapture error in shims.ts (commit 0512a89)
- [x] Second CI passed (run 22331077225)

### Task 5: Verify deployment works
- [x] Health endpoint returns 200 (__health works)
- [x] Root path returns 503/1102 — CPU time limit exceeded (Bundled usage model, 50ms hard limit)
- [x] Root cause identified: Cloudflare account uses Bundled usage model with 50ms CPU limit; PHP-WASM initialization exceeds this. Need human to switch to Standard usage model.
- [x] Created needs-human task for switching to Standard usage model

## Notes for the Agent
- The custom PHP 8.5 WASM build (`php-wasm-build/build.sh`) requires Docker and takes 20-60 minutes. Do NOT attempt to run it. The integration of the custom build is a separate epic.
- The `php-cgi-wasm` npm package currently provides PHP 8.3. This is what the worker runs.
- The php8.3 filenames in the code are correct — they match the npm package filenames.
- Run `vendor/bin/pint --dirty --format agent` after modifying PHP files.
- Run `vendor/bin/pest --compact` to verify tests pass.
- Use `gh` CLI for monitoring CI.

## Progress Log
- Iteration 1: Fixed wrangler.jsonc.stub — removed nodejs_compat, added observability section (commit 70977ef)
- Iteration 2: Added OPcache INI settings to worker.ts stub as newline-separated array (commit 509ad16)
- Iteration 3: Added PHPDoc to EXTENSION_REGISTRY in BuildDirectory.php and inline comments to build-app.mjs explaining php8.3 filenames and ABI incompatibility (commit 7d08df0)
- Iteration 4: Merged epic branch to main, pushed, CI passed. Live site returned 500 "addEventListener(): useCapture must be false" — fixed by wrapping globalThis.addEventListener in shims.ts to force useCapture=false (commit 0512a89). After re-deploy, __health returns 200 but root / returns 1102 (CPU time exceeded). Root cause: Bundled usage model has 50ms hard CPU limit; PHP-WASM init exceeds this. Created needs-human task for user to switch to Standard usage model in Cloudflare dashboard.
