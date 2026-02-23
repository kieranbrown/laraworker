# Epic: Fix CI build pipeline and optimize bundle size (e-75b83c)

## Goal

Fix the deploy-demo GitHub Actions workflow failure and ensure the build pipeline produces minimal, production-only bundles in all environments (CI, local, path repos).

## Context

- CI run: https://github.com/kieranbrown/laraworker/actions/runs/22319095996/job/64572182971
- PHP 8.5 + OPcache already implemented and working
- Bundle currently 2,596 KiB gzipped (fits 3MB free tier)
- The build fails in CI because staging vendor doesn't resolve path repositories

## Root Cause Analysis

`BuildCommand.php:92-131` (`prepareProductionVendor()`) creates `.laraworker/vendor-staging/` and copies `composer.json` + `composer.lock`, then runs `composer install --no-dev`. In CI, the demo app's `composer.json` has a path repository (`path ../laraworker-package`) — when copied to staging, the relative path doesn't resolve. Composer fails or produces incomplete vendor. `build-app.mjs:40-46` falls back to main vendor (includes dev packages), then the dev-package guard at line 605 catches faker + 1833 files and exits with error.

## Approach

### Task 1: Fix staging vendor with path repository support

**File**: `src/Console/BuildCommand.php`

`prepareProductionVendor()` must handle path repositories correctly:
- Before copying composer.json to staging, rewrite path repository URLs to be absolute paths (so they resolve from any directory)
- OR: skip staging entirely and use `composer install --no-dev` in the main vendor dir, then restore after build
- OR: copy the actual path repo source into staging alongside composer.json

Recommended approach: rewrite relative path repo URLs to absolute before copying to staging. Parse `composer.json`, find `repositories[].url` where `type=path`, resolve against `$basePath`, write modified `composer.json` to staging.

**Safety**: This task only modifies `src/Console/BuildCommand.php`. No workspace-destructive operations. The `recursiveRmdir()` at line 97 only targets `.laraworker/vendor-staging/` (a build artifact). Agent MUST NOT run commands that delete files outside `.laraworker/`.

### Task 2: Review and optimize bundle size

**Files**: `stubs/build-app.mjs`, `config/laraworker.php`

Review the current exclusion patterns and optimization pipeline:
- Audit whether all DEFAULT_EXCLUDE_PATTERNS are effective
- Check if there are additional large files/directories that could be excluded
- Review Carbon locale stripping (currently keeps only `en.php`)
- Consider whether `STRIP_WHITESPACE` should default to `true` in CI/deploy
- Ensure the WASM binary estimate (3.4 MB) is accurate for PHP 8.5 + OPcache
- Verify no unnecessary npm packages are bundled

Target: keep total bundle under 3MB (free tier) or absolute max 10MB.

**Safety**: Read-only analysis first, then targeted changes to exclusion patterns. No workspace-destructive operations.

### Task 3: Review epic — verify CI passes and bundle is optimal

Blocked by tasks 1 and 2.

Acceptance criteria:
1. `deploy-demo` workflow passes (push to branch, observe green CI)
2. No dev packages in bundle (faker, phpunit, pest, mockery, pint, etc.)
3. Bundle size reported by build-app.mjs is under 3MB compressed
4. Staging vendor works with both path repos (CI) and regular repos (user apps)
5. All existing tests pass: `vendor/bin/pest --compact`

## Files Modified

| File | Change |
|------|--------|
| `src/Console/BuildCommand.php` | Fix `prepareProductionVendor()` path repo resolution |
| `stubs/build-app.mjs` | Potential exclusion pattern improvements |
| `config/laraworker.php` | Potential default tweaks |
| `tests/` | Add test for path repo vendor staging if feasible |

## Safety Constraints (CRITICAL)

Agents MUST follow these rules:
- **NEVER** delete or modify files outside the project repo
- **NEVER** run `rm -rf` on any path outside `.laraworker/` build artifacts
- **NEVER** touch `.fuel/agent.db` or `.fuel/config.yml`
- Before running any build/install command, verify the working directory is correct
- If a command could affect the workspace, skip it and add a `--labels=needs-human` task instead
- The `recursiveRmdir()` in BuildCommand only targets `.laraworker/vendor-staging/` — this is safe

## Implementation Notes

### Task 1 (f-7120e7) — Completed
- Replaced `copy()` of composer.json with JSON parse+rewrite approach
- Path repos with relative URLs are resolved to absolute via `realpath($basePath . '/' . $url)` before writing to staging
- Only non-absolute URLs are rewritten (those not starting with `/`)
- Added `$this->components->warn()` with stderr output when composer install fails in staging — previously returned `false` silently
- Gotcha: `realpath()` returns `false` if the path doesn't exist; we skip rewriting in that case so it falls through to composer's own error handling

### Task 2 (f-b869e5) — Completed
- Added 20 new exclusion patterns to `DEFAULT_EXCLUDE_PATTERNS` in `stubs/build-app.mjs`
- New categories: example/benchmark/fixture dirs, CI service configs, package manager files, Symfony polyfill stubs
- Symfony polyfill PHP version stubs (`Resources/stubs/`) safe to exclude: on PHP 8.5 all native classes exist, autoloader never loads stubs
- `vendor/composer/installed.php` NOT excluded (used by `InstalledVersions` — some packages check versions at runtime)
- `database/` directory NOT changed: controlled by `include_dirs` not exclusion patterns, and some apps may need it
- Gotcha: patterns without `$` anchor (e.g. `phpunit\.xml`) already match `.dist` variants — don't double-exclude

## Interfaces Created
<!-- Tasks: document interfaces/contracts created -->