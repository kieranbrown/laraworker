# Post-Refactor Repo Cleanup

## Context
The repo was refactored from a Laravel application into a standalone Composer package (`kieranbrown/laraworker`). Several files, configs, and directories are leftover from the old app structure and need cleaning up.

## Tasks

### 1. Fix .gitignore for build artifacts and runtime files
Add missing entries so `git status` stays clean:
- `.cloudflare/*.wasm` and `.cloudflare/php-cgi.mjs` (WASM binaries copied from node_modules during install)
- `.cloudflare/.env.production` and `.cloudflare/build-config.json` (generated during build)
- `public/build/` (Vite output)
- `storage/` (Laravel runtime cache)
- `.env` (local environment)

### 2. Delete duplicate .codex/ directory
`.codex/skills/` is an exact duplicate of `.claude/skills/`. Remove `.codex/` entirely — it serves no purpose.

### 3. Delete placeholder example tests
Remove:
- `tests/Unit/ExampleTest.php`
- `tests/Feature/ExampleTest.php`
These are Laravel scaffolding leftovers with no real assertions.

### 4. Fix .fuel/setup.sh for package context
Current script expects a Laravel app (.env.example, database/database.sqlite, php artisan migrate). Replace with package-appropriate setup:
- `composer install --no-interaction`
- `bun install` (for node deps used by stubs)
- Verify vendor/bin/pest exists

### 5. Fix .fuel/run.yml for package context
Current services (`php artisan serve`, `queue:listen`, `pail`, `bun run dev`) don't apply to a package. Either:
- Remove the file entirely (no long-running services needed for package dev), OR
- Define only a test watcher if useful

### 6. Fix .fuel/quality-gate
Current script passes staged PHP file paths as arguments to `vendor/bin/pest` — Pest doesn't filter by arbitrary file paths like that. Should just run `vendor/bin/pest --compact` for the full test suite. Keep the Pint section as-is (it correctly formats only staged files).

### 7. Discard uncommitted changes to .claude/skills/ and .codex/skills/
The working copy has modified fuel-create-plan SKILL.md files (adding ASCII diagram guidance). These are Fuel tooling changes unrelated to this repo — discard them.

## Acceptance Criteria
- `git status` shows no untracked build artifacts (.cloudflare/*.wasm, public/build/, storage/)
- No .codex/ directory exists
- No ExampleTest.php files exist
- `.fuel/setup.sh` runs successfully on a fresh clone (no references to .env.example, database/, migrations)
- `.fuel/run.yml` either removed or only contains package-relevant services
- `.fuel/quality-gate` runs Pest correctly (full suite, not file-filtered)
- All existing tests pass (`vendor/bin/pest --compact`)

## Smoketesting
1. `vendor/bin/pest --compact` — all tests pass
2. `vendor/bin/pint --test` — no formatting issues
3. `git status` — clean working tree after cleanup
4. Run `.fuel/setup.sh` from scratch — completes without errors