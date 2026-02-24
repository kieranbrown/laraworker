# Agent Development Guide

This document provides guidance for AI agents working on the laraworker project.

## Quick Local Iteration Workflow (Fast Feedback)

For rapid development and testing, use local wrangler commands instead of the slow CI loop (push → CI build → deploy → test).

### Local Development Server (Instant Feedback)

Build once, then start the local dev server:

```bash
# From the playground (or any Laravel app with laraworker installed)
cd playground
php artisan laraworker:build
cd .laraworker && npx wrangler dev
```

The worker will be available at `http://localhost:8787`. Test with:

```bash
curl http://localhost:8787
```

**Key benefits:**
- No git push required
- No CI wait time (5-10 minutes → instant)
- Live reload on file changes

### Direct Deploy (Skip CI Entirely)

When local testing passes, deploy directly without CI:

```bash
# From the .laraworker directory
cd playground/.laraworker
npx wrangler deploy
```

**Deploy time:** ~30 seconds vs 5+ minutes via CI

### Live Production Logs

Monitor deployed workers in real-time:

```bash
cd playground/.laraworker
npx wrangler tail
```

### CI as Final Validation Only

Only push to git and run CI as a final validation step, not for every iteration:

1. **Iterate locally** with `wrangler dev` (fast)
2. **Deploy directly** with `wrangler deploy` when ready (30s)
3. **Push to git** and let CI run as final validation (slow, but confirms everything)

### Artisan Commands Available

The following commands are available in any app using laraworker:

- `php artisan laraworker:dev` — Builds app + starts `wrangler dev` locally
- `php artisan laraworker:build` — Just builds (generates `.laraworker/` directory)
- `php artisan laraworker:deploy` — Builds + runs `wrangler deploy` (direct deploy, no CI)
- `bun run dev:worker` — Same as `laraworker:dev` (via package.json)
- `bun run deploy:worker` — Same as `laraworker:deploy` (via package.json)

## Testing Changes Locally

The playground is an ephemeral Laravel project used to test laraworker changes in a real environment.

### First-time setup

```bash
scripts/playground-setup.sh --force
```

This creates a fresh Laravel project in `playground/`, installs laraworker as a path repository, and runs the install command.

### After making changes to the package

When you modify laraworker code, you need to rebuild and test:

```bash
# Build the project for Cloudflare Workers
scripts/playground-build.sh

# Or run the full smoke test (builds + starts dev server + tests)
scripts/playground-smoke-test.sh
```

### Full verification

```bash
scripts/playground-smoke-test.sh
```

This script:
1. Builds the playground project
2. Starts the dev server in the background
3. Runs HTTP smoke tests against localhost:8787
4. Reports pass/fail status
5. Cleans up the dev server

### Teardown

```bash
scripts/playground-teardown.sh
```

Removes the playground directory entirely. Use this when you're done testing or need a clean slate.

## Common Code Locations

- `src/` — Main composer package source
- `src/Console/` — Artisan commands (InstallCommand.php, BuildCommand.php, DevCommand.php, DeployCommand.php, StatusCommand.php)
- `stubs/` — Worker stubs (worker.ts, build-app.mjs, wrangler.jsonc.stub)
- `config/laraworker.php` — Package configuration
- `scripts/` — Playground and testing scripts
- `php-wasm-build/` — Custom PHP WASM builder toolchain
- `tests/` — Pest test suite
