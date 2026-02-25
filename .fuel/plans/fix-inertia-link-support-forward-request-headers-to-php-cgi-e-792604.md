# Fix Inertia Link Support — Forward Request Headers to PHP CGI

## Problem

Inertia `<Link>` components don't work because **PhpCgiBase.mjs does not forward HTTP request headers to PHP as CGI env vars**.

When Inertia's client-side router intercepts a click on a `<Link>`, it makes an XHR fetch with:
- `X-Inertia: true`
- `X-Inertia-Version: <version>`
- `X-Requested-With: XMLHttpRequest`

PHP CGI expects these as `HTTP_X_INERTIA`, `HTTP_X_INERTIA_VERSION`, `HTTP_X_REQUESTED_WITH` env vars. But `PhpCgiBase.mjs` only sets `HTTP_HOST` and `HTTP_COOKIE` — all other headers are silently dropped.

**Secondary issue:** `SESSION_DRIVER=array` means CSRF tokens don't persist between requests, causing 419 errors on any non-GET Inertia navigation.

## Root Cause Files

- `playground/.laraworker/PhpCgiBase.mjs:620-662` — Only sets HTTP_HOST + HTTP_COOKIE, ignores all other request headers
- `playground/.laraworker/breakoutRequest.mjs` — Extracts url/method/body but NOT headers
- `config/laraworker.php:224` — SESSION_DRIVER=array loses CSRF tokens

## Solution

### Task 1: Forward request headers to PHP in worker stub

Modify `stubs/worker.ts.stub` to iterate over the incoming `Request.headers` and call `putEnv()` for each one as `HTTP_<NAME>` (uppercased, hyphens to underscores). This must happen in the worker stub level since PhpCgiBase.mjs is an upstream dependency we don't control.

**Approach:** Override `_beforeRequest()` or wrap `instance.request()` in worker.ts.stub to inject headers. Alternatively, use the `env` option in PhpCgiCloudflare constructor — but this only works for static values. The per-request approach is needed.

**Key detail:** PhpCgiBase has a `putEnv` function accessible via `php.ccall('wasm_sapi_cgi_putenv', ...)`. The worker stub needs to call this for each request header before `instance.request()` processes the request.

**Best approach:** Subclass `request()` in `PhpCgiCloudflare` (stubs/php.ts.stub) to extract and forward headers. OR patch PhpCgiBase.mjs at build time to iterate `request.headers` in the request method.

### Task 2: Switch SESSION_DRIVER to cookie

Change `config/laraworker.php` env_overrides from `SESSION_DRIVER=array` to `SESSION_DRIVER=cookie`. The cookie driver stores session data in encrypted cookies — no server-side storage needed. PhpCgiBase's CookieJar already handles Set-Cookie persistence between requests within a Worker isolate.

### Task 3: Revert Link→a tag workaround and restore Inertia navigation

Revert commit 4497c3a changes in `playground/resources/js/Layouts/AppLayout.vue` — re-import `Link` from `@inertiajs/vue3` and replace `<a>` tags back to `<Link>` components.

### Task 4: End-to-end verification ✅

Build and test: clicking Inertia Links should produce XHR requests (visible in Network tab), PHP should return JSON with `X-Inertia` header, and page transitions should be smooth without full reloads.

## Verification Results

### Issues Found & Fixed During Review

1. **Playground config out of sync**: `playground/config/laraworker.php` was missing `storage/framework/views` from `include_dirs` and still had `SESSION_DRIVER=array`. The package default config (`config/laraworker.php`) had been updated but the playground's published copy was stale.
   - **Root cause**: Compiled Blade views from `view:cache` were stored in `storage/framework/views/` but not included in the tar, so WASM had to recompile views at runtime where custom Blade directives (`@inertia`, `@vite`) failed to register.
   - **Fix**: Added `storage/framework/views` to playground's `include_dirs` and updated `SESSION_DRIVER` to `cookie`.

2. **Stale composer.json path repository**: `playground/composer.json` referenced a non-existent mirror path (`e-a29302`), causing `composer update --no-dev` to fail in the staging vendor step.
   - **Fix**: Updated path to current mirror (`e-792604`).

3. **Test assertion mismatch**: `tests/Feature/BuildCommandTest.php` expected `SESSION_DRIVER=array` but config was changed to `cookie`.
   - **Fix**: Updated test to expect `SESSION_DRIVER=cookie`.

### Gotchas for Future Agents

- **Published configs drift**: When changing `config/laraworker.php` defaults, also update `playground/config/laraworker.php` — it's a published copy that overrides the package default.
- **Compiled views MUST be in the tar**: Without `storage/framework/views` in `include_dirs`, Blade directive registration fails silently in WASM. The `view.relative_hash=true` setting makes cache hashes portable between build-time and runtime paths.
- **Composer path repos in mirrors**: The playground's `composer.json` `repositories[].url` must match the current mirror path.

### Verified Acceptance Criteria

- ✅ Build playground succeeds
- ✅ All 66 package tests pass (183 assertions)
- ✅ Inertia XHR requests return JSON with `X-Inertia: true` header
- ✅ No 419 CSRF errors (cookie session driver works)
- ✅ Browser navigation is smooth (no full page reloads)
- ✅ All 4 routes work: /, /features, /architecture, /performance
- ✅ Deployed to production (laravel.kieranbrown.workers.dev)
- ✅ Production site navigation works with Inertia
- ✅ laraworker.kswb.dev returns 200 (same worker)