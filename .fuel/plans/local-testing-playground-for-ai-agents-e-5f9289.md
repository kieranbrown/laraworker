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
- A `Makefile` or shell script (`scripts/playground.sh`) orchestrates setup
- Must work headless (no interactive prompts) for AI agent usage
- Should support teardown/reset for clean re-testing

## Scripts

| Script | Purpose |
|--------|---------|
| `scripts/playground-setup.sh` | Scaffold Laravel project, install laraworker, run install command |
| `scripts/playground-build.sh` | Run `php artisan laraworker:build` inside playground |
| `scripts/playground-dev.sh` | Start `php artisan laraworker:dev` (interactive) |
| `scripts/playground-teardown.sh` | Remove playground directory |
| `scripts/playground-smoke-test.sh` | End-to-end: build → start dev server → HTTP assertions → cleanup |

## Patterns

- All scripts use `set -euo pipefail` and derive `PLAYGROUND_DIR` from script location
- Dev server runs on `http://localhost:8787` (wrangler default)
- Smoke test uses `trap EXIT` to guarantee background process cleanup
- Scripts check for playground existence and error early with actionable messages

## Tasks
1. ~~Add playground infrastructure (gitignore, setup script)~~ ✅
2. ~~Create smoke test script that verifies build + dev server works~~ ✅
3. Document playground usage in AGENTS.md or similar