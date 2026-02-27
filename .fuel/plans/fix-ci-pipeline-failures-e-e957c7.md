# Fix CI Pipeline Failures

## Context
CI run https://github.com/kieranbrown/laraworker/actions/runs/22481839326 has two failures:

### Issue 1: Deploy Demo — Hardcoded Composer Path
The playground's `composer.json` has a path repository pointing to `/Users/kieran/Code/github.com/kieranbrown/laraworker`. The CI step runs `composer config repositories.laraworker path ../` but then `composer install` uses the lock file which still references the absolute path. Composer's PathDownloader fails because the local machine path doesn't exist in CI.

**Fix:** In `.github/workflows/ci.yml`, change `composer install --no-interaction` to `composer update kieranbrown/laraworker --no-interaction` in the "Point playground at local laraworker package" step. This forces Composer to re-resolve the package using the newly configured `path ../` repository instead of the stale lock file entry.

### Issue 2: Tests — DeleteCommandTest calls real wrangler
3 tests in `tests/Feature/DeleteCommandTest.php` actually invoke `npx wrangler` via `Symfony\Component\Process\Process`, which isn't installed in CI. Tests take 6-14s each (real subprocess timeout) then fail with exit code 1.

Failing tests:
- `command is registered and callable` (line 7)
- `safety guard allows deletion with force flag` (line 25)
- `normal worker name proceeds without force with confirmation` (line 36)

**Fix:** Mock or fake the `Process` class in tests so wrangler isn't actually invoked. The tests should verify command logic (safety guards, confirmation prompts, output), not that wrangler is installed. Options:
- Use `Process::fake()` / mockery to mock the Symfony Process
- Or refactor DeleteCommand to use a testable wrapper around Process
- Simplest: spy on Process construction, verify correct args passed, stub success/failure responses

## Verification (f-d51d08)

✅ **Tests verified:** All 93 tests pass including DeleteCommandTest (5 tests)
✅ **CI workflow verified:** `.github/workflows/ci.yml:58` correctly uses `composer update kieranbrown/laraworker --no-interaction`
✅ **No regressions:** All 232 assertions pass across 8 test files

Both fixes applied successfully. Pipeline should pass on next CI run.