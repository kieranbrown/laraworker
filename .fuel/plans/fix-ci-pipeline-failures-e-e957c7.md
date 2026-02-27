# Fix CI Pipeline Failures

## Context
CI run https://github.com/kieranbrown/laraworker/actions/runs/22481839326 has two failures:

### Issue 1: Deploy Demo — Hardcoded Composer Path
The playground's `composer.json` has a path repository pointing to `/Users/kieran/Code/github.com/kieranbrown/laraworker`. The CI step runs `composer config repositories.laraworker path ../` but then `composer install` uses the lock file which still references the absolute path. Composer's PathDownloader fails because the local machine path doesn't exist in CI.

**Fix:** In `.github/workflows/ci.yml`, change `composer install --no-interaction` to `composer update kieranbrown/laraworker --no-interaction` in the "Point playground at local laraworker package" step. This forces Composer to re-resolve the package using the newly configured `path ../` repository instead of the stale lock file entry.

### Issue 2: Tests — DeleteCommandTest calls real wrangler [DONE]
3 tests in `tests/Feature/DeleteCommandTest.php` actually invoke `npx wrangler` via `Symfony\Component\Process\Process`, which isn't installed in CI. Tests take 6-14s each (real subprocess timeout) then fail with exit code 1.

**Solution applied:**
- Extracted `protected createProcess()` method in `src/Console/DeleteCommand.php` (line ~105) so Process instantiation is overridable
- In `tests/Feature/DeleteCommandTest.php`, created `fakeDeleteCommand()` helper that registers an anonymous subclass overriding `createProcess()` to return Mockery mocks
- Tests that hit wrangler (lines 29, 46, 56) call `fakeDeleteCommand()` before `$this->artisan()`
- Tests that don't reach wrangler (safety guard, cancellation) remain unchanged

**Pattern for future commands:** If another command uses `new Process(...)` directly and needs test mocking, extract a `createProcess()` method and use the same anonymous-subclass + `app()->singleton()` pattern from DeleteCommandTest.