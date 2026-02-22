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

### 3. Refactor BuildCommand to generate .laraworker/ at build time ✅ DONE
- ✅ Removed `.cloudflare` existence check from handle()
- ✅ handle() now: instantiates BuildDirectory → ensureDirectory → copyStubs → generatePhpTs → generateWranglerConfig → generateEnvProduction → writeBuildConfig (all in a single "Preparing build directory" task)
- ✅ Removed ensureEnvProduction() and writeBuildConfig() methods (delegated to BuildDirectory)
- ✅ optimizeForProduction() uses `$this->buildDirectory->path('.env.production')` for env file
- ✅ runBuildScript() uses `$this->buildDirectory->path('build-app.mjs')` for node script
- ✅ Zero `.cloudflare` references remain in BuildCommand.php
- ✅ Tests updated in tests/Feature/BuildCommandTest.php — all use BuildDirectory::DIRECTORY constant
- ✅ Pre-existing ConfigTest failure (strip_whitespace default mismatch) is unrelated

### 4. Simplify InstallCommand ✅ DONE
- ✅ Removed: publishStubs(), generatePhpTs(), generateWranglerConfig(), generateEnvProduction(), writeBuildConfig(), resolvePhpWasmImport()
- ✅ Removed private $extensionRegistry — now uses BuildDirectory::EXTENSION_REGISTRY for npm packages
- ✅ handle() flow: publishConfig → updatePackageJson → updateGitignore → installNpmDependencies → runInitialBuild → verifyInstallation
- ✅ runInitialBuild() now calls `$this->call('laraworker:build')` instead of `node .cloudflare/build-app.mjs`
- ✅ updateGitignore() writes single `/.laraworker/` entry instead of multiple `.cloudflare/*` entries
- ✅ verifyInstallation() checks config/laraworker.php + .laraworker/ directory existence only
- ✅ detectLegacyDirectory() warns if .cloudflare/ exists
- ✅ Tests updated in tests/Feature/InstallCommandTest.php

### 5. Update DevCommand and DeployCommand
- Change working directory from `.cloudflare` to `.laraworker`
- DeployCommand: read wrangler config from `.laraworker/wrangler.jsonc`
- Remove `.cloudflare` existence checks

### 6. Update StatusCommand
- Change all `.cloudflare` references to `.laraworker`
- WASM detection paths, bundle size calculations, etc.

### 7. Update build-app.mjs paths
- Currently uses `import.meta.dirname` (which equals `.cloudflare/`). Since we copy it to `.laraworker/`, no changes needed to the script itself — `import.meta.dirname` will resolve to `.laraworker/` at runtime.
- Update ROOT resolution to still point to project root (`..` from `.laraworker/`)

### 8. Review task: end-to-end verification
- Full install → build → dev cycle works
- No `.cloudflare/` directory created anywhere
- Config changes in laraworker.php are reflected in builds
- Existing playground works after migration

## Migration Path
- `laraworker:install` detects existing `.cloudflare/` and warns user to delete it
- Since `.laraworker/` is fully generated, no migration of files needed
- User-customized wrangler config (account_id) moves to `config/laraworker.php`

## Risk Areas
- `build-app.mjs` uses `import.meta.dirname` extensively — copying to `.laraworker/` preserves this
- wrangler.jsonc account_id is currently hand-edited by users — needs clear migration docs
- Inertia SSR bundle import path in worker.ts may need user customization — consider making it configurable