# Agent Development Guide

This document provides guidance for AI agents working on the laraworker project.

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
