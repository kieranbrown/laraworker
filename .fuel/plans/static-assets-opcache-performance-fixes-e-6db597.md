# Static Assets & OPcache Performance Fixes

## Problem Statement

Two performance issues identified on the deployed demo (laraworker.kswb.dev):

### 1. Static files invoke the PHP WASM worker
Files like `/robots.txt` and `/favicon.ico` are NOT deployed as Cloudflare Static Assets. The build only copies `public/build/` (Vite output) to `dist/assets/`. All other public files hit the worker → boot PHP WASM → ~0.5-2.8s response for a static file that should be <50ms.

### 2. OPcache config mismatch & no diagnostics
OPcache IS compiled into the WASM binary and enabled, but:
- `stubs/worker.ts` hardcodes 8MB memory; `config/laraworker.php` says 32MB
- Config values are never templated into the worker stub
- No runtime diagnostic to verify OPcache is actually working
- Performance doc is outdated (says OPcache unavailable)

## Tasks

| Task | ID | Complexity | Description |
|------|----|-----------|-------------|
| Copy public/ static files to Static Assets | f-a69f1f | moderate | Build copies public/ files (except index.php, build/) to dist/assets/ |
| Template OPcache INI into worker.ts | f-7e9db7 | moderate | Replace hardcoded INI with config-driven values from laraworker.php |
| OPcache diagnostic endpoint | f-3530a8 | simple | Add /__opcache-status endpoint (behind env var) for production debugging |
| Review | f-0859b3 | complex | Verify all fixes, test deployment, confirm performance improvement |

## Completed: f-a69f1f — Copy public/ static files to Static Assets

**Files modified:**
- `config/laraworker.php` — Added `public_assets` config option (default `true`)
- `src/BuildDirectory.php` — Added `public_assets` to `writeBuildConfig()` output
- `stubs/build-app.mjs` — Added `copyPublicFiles()` function + invocation after Vite copy block

**How it works:**
- `copyPublicFiles()` recursively scans `ROOT/public/`, skipping `index.php` and `build/`
- Copies remaining files to `DIST_DIR` preserving relative paths (e.g. `public/robots.txt` → `dist/assets/robots.txt`)
- Controlled by `config.public_assets` from build-config.json (defaults to `true`)
- Logs each copied file during build

**Patterns to follow:**
- Config flows: `config/laraworker.php` → `BuildDirectory::writeBuildConfig()` → `build-config.json` → `build-app.mjs`
- Static assets land in `DIST_DIR` which maps to `assets.directory` in wrangler.jsonc

## Expected Outcome
- Static files served in <50ms from CF edge
- OPcache config matches what's in config/laraworker.php
- Ability to verify OPcache is working in production
- Updated performance documentation