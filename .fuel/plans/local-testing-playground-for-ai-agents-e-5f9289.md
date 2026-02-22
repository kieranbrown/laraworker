# Local Testing Playground for AI Agents

## Goal
Enable AI agents to fully test laraworker changes locally by spinning up a real Laravel project, installing the package, building, and running it on a local Cloudflare Workers dev environment.

## Approach
Create a `playground/` directory (gitignored) with automation scripts that:
1. Scaffold a fresh Laravel project
2. Install `kieranbrown/laraworker` as a path repository
3. Run `php artisan laraworker:install`
4. Run `php artisan laraworker:build`
5. Run `php artisan laraworker:dev` to start local wrangler dev server
6. Provide a smoke test script that hits the local server and verifies a 200 response

## Key Decisions
- `playground/` added to .gitignore — ephemeral, agents create/destroy as needed
- Shell scripts in `scripts/` directory (not Makefile) — simpler for AI agent invocation
- Must work headless (no interactive prompts) for AI agent usage
- Should support teardown/reset for clean re-testing
- Path repository points to project root (the package lives at repo root, not in packages/)
- `bun install` run explicitly after `laraworker:install` — the install command's own npm install may not fully complete
- `composer dump-autoload` run after install to fix autoloader corruption (install's `--no-dev` build step strips dev packages from autoloader cache)

## Completed — Playground Scripts (f-1abf91)

### Files Created
- `.gitignore` — added `/playground/` entry
- `scripts/playground-setup.sh` — creates fresh Laravel project, installs laraworker via path repo, runs install + bun install + autoloader fix
- `scripts/playground-build.sh` — runs `laraworker:build` inside playground
- `scripts/playground-dev.sh` — runs `laraworker:dev` inside playground (foreground process), checks for build artifacts first
- `scripts/playground-teardown.sh` — removes playground/ directory

### Gotchas
- `laraworker:install` runs an initial build that calls `composer dump-autoload --classmap-authoritative --no-dev`, which corrupts the autoloader for dev packages (e.g., Laravel Pail). Must run `composer dump-autoload` after install to restore.
- `laraworker:install` installs npm deps via bun but node_modules may not persist (observed empty node_modules after install). Explicit `bun install` after install is required.
- All scripts use `set -euo pipefail` and resolve paths relative to script location via `BASH_SOURCE`.

### Patterns
- Scripts resolve `PROJECT_ROOT` from `SCRIPT_DIR/..` so they work from any working directory
- `--force` flag on setup for non-interactive reset (AI agent friendly)
- Build and dev scripts validate preconditions before running (playground exists, build artifacts exist)

## Tasks
1. ~~Add playground infrastructure (gitignore, setup script)~~ ✅
2. Create smoke test script that verifies build + dev server works
3. Document playground usage in AGENTS.md or similar
