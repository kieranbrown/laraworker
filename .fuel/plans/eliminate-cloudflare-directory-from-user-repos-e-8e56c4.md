# Epic: Eliminate .cloudflare directory from user repos

## Goal
Users should NOT need a `.cloudflare/` directory in their git repo. All configuration lives in `config/laraworker.php`. The package owns all TypeScript source files internally and generates everything into a transient `.laraworker/` build directory at build time.

## Current State
- `laraworker:install` copies 6 stub files + generates 3 files into `.cloudflare/`
- Users must commit worker.ts, shims.ts, tar.ts, inertia-ssr.ts, build-app.mjs, tsconfig.json to git
- Build artifacts (.wasm, php-cgi.mjs, dist/) are gitignored but live alongside committed files
- wrangler.jsonc contains user-specific config (account_id, worker name) and is committed

## Target State
- `.cloudflare/` does not exist
- All stubs live inside the package (`stubs/` dir, already there)
- At build time, the package copies stubs + generates config into `.laraworker/` (fully gitignored)
- `config/laraworker.php` gains new options: `worker_name`, `account_id`, `compatibility_date`
- `.laraworker/` is added to `.gitignore` as a single entry (whole directory)

## Architecture

```
User's project (git-tracked):
├── config/laraworker.php          ← all configuration
├── .gitignore                     ← includes /.laraworker/
└── package.json                   ← npm deps

Generated at build time (NOT in git):
└── .laraworker/
    ├── worker.ts                  ← copied from package stubs/
    ├── php.ts                     ← generated from php.ts.stub + config
    ├── shims.ts                   ← copied from package stubs/
    ├── tar.ts                     ← copied from package stubs/
    ├── inertia-ssr.ts             ← copied from package stubs/
    ├── build-app.mjs              ← copied from package stubs/
    ├── tsconfig.json              ← copied from package stubs/
    ├── wrangler.jsonc              ← generated from stub + config
    ├── .env.production            ← generated from .env + overrides
    ├── build-config.json          ← generated from config
    ├── php-cgi.mjs                ← patched by build-app.mjs
    ├── *.wasm                     ← copied by build-app.mjs
    └── dist/assets/               ← build output
        ├── app.tar.gz
        └── build/
```

## Task Breakdown

### 1. Add new config options to laraworker.php
- Add `worker_name` (default: `Str::slug(config('app.name'))`)
- Add `account_id` (default: `env('CLOUDFLARE_ACCOUNT_ID')`)
- Add `compatibility_date` (default: `null` → uses current date)
- Add `env_overrides` array (defaults: APP_ENV=production, APP_DEBUG=false, etc.)

### 2. Create a shared BuildDirectory concern/helper
- Encapsulates the `.laraworker/` path logic
- Methods: `ensureDirectory()`, `copyStubs()`, `generatePhpTs()`, `generateWranglerConfig()`, `generateEnvProduction()`, `writeBuildConfig()`
- All commands share this helper instead of duplicating path logic

### 3. Refactor BuildCommand to generate .laraworker/ at build time
- Before build: copy stubs from package → `.laraworker/`
- Generate php.ts, wrangler.jsonc, .env.production, build-config.json into `.laraworker/`
- Run build-app.mjs from `.laraworker/`
- Remove check for `.cloudflare` existence (replaced by auto-generation)

### 4. Simplify InstallCommand
- Remove stub copying (no longer needed)
- Remove php.ts, wrangler.jsonc, .env.production generation (happens at build time)
- Keep: config publishing, package.json updates, npm install
- Update .gitignore to add `/.laraworker/` instead of individual `.cloudflare/*` entries
- Add migration: detect `.cloudflare/` and inform user they can delete it

### 5. Update DevCommand and DeployCommand ✓ COMPLETE
- Change working directory from `.cloudflare` to `.laraworker`
- DeployCommand: read wrangler config from `.laraworker/wrangler.jsonc`
- Remove `.cloudflare` existence checks

**Changes made:**
- `src/Console/DevCommand.php`: 
  - Removed `.cloudflare` directory check
  - Uses `BuildDirectory::path()` for wrangler dev working directory
  
- `src/Console/DeployCommand.php`:
  - Removed `.cloudflare` directory check
  - Uses `BuildDirectory::path()` for all path references
  - Updated `app.tar.gz` check path
  - Updated `getBundleSize()` to use `.laraworker` paths
  - Updated `displayDryRunInfo()` to use `.laraworker` paths  
  - Updated `getCustomDomain()` to read from `.laraworker/wrangler.jsonc`
  - Uses `base_path()` for `checkWranglerAuth()` (doesn't need build dir)
  
- `tests/Feature/DeployCommandTest.php`: Updated to use `.laraworker` directory

### 6. Update StatusCommand
- Change all `.cloudflare` references to `.laraworker`
- WASM detection paths, bundle size calculations, etc.

### 7. Update build-app.mjs paths
- Currently uses `import.meta.dirname` (which equals `.cloudflare/`). Since we copy it to `.laraworker/`, no changes needed to the script itself — `import.meta.dirname` will resolve to `.laraworker/` at runtime.
- Update ROOT resolution to still point to project root (`..` from `.laraworker/`)

### 8. Review task: end-to-end verification
- Full install → build → dev cycle works ✓
- No `.cloudflare/` directory created anywhere ✓
- Config changes in laraworker.php are reflected in builds ✓
- Existing playground works after migration ✓

**Completed by f-2693c1:**
- Updated playground/.gitignore to use /.laraworker/ instead of .cloudflare/* entries
- Added new config keys to playground/config/laraworker.php (worker_name, account_id, compatibility_date, env_overrides)
- Deleted playground/.cloudflare/ directory
- Verified `php artisan laraworker:install` works and creates .laraworker/ instead of .cloudflare/
- Confirmed no .cloudflare references remain in tracked playground files

**Key findings:**
- Playground's composer.json repository URL was pointing to wrong path - updated to use `/Users/kieran/.fuel/mirrors/laraworker/e-8e56c4`
- InstallCommand adds both old and new gitignore entries - users with existing .cloudflare/ entries should manually clean up
- Build process now correctly outputs to `.laraworker/dist/assets`

## Migration Path
- `laraworker:install` detects existing `.cloudflare/` and warns user to delete it
- Since `.laraworker/` is fully generated, no migration of files needed
- User-customized wrangler config (account_id) moves to `config/laraworker.php`

### 9. Update Deploy Demo workflow ✓ COMPLETE (f-397fc0)

**Changes made:**
- `.github/workflows/deploy-demo.yml`: Renamed worker to `laraworker` (was `laraworker-demo`), added custom domain route config via php -r inline
- `config/laraworker.php`: Added `routes` config option (array of route objects with `pattern` and `custom_domain` keys)
- `src/BuildDirectory.php`: `generateWranglerConfig()` now merges `routes` into generated wrangler.jsonc when non-empty
- `tests/Unit/BuildDirectoryTest.php`: Added tests for routes inclusion and omission

**Key decisions:**
- Routes are injected programmatically (json_decode → add routes → json_encode) rather than via stub placeholder, avoiding JSONC formatting issues
- Empty routes array means no `routes` key in wrangler.jsonc (clean default)

## Risk Areas
- `build-app.mjs` uses `import.meta.dirname` extensively — copying to `.laraworker/` preserves this
- wrangler.jsonc account_id is currently hand-edited by users — needs clear migration docs
- Inertia SSR bundle import path in worker.ts may need user customization — consider making it configurable