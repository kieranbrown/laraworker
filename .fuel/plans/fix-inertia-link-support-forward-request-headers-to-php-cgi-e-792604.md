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

### Task 1: Forward request headers to PHP in worker stub ✅ COMPLETED

**Implementation:** Overrode `request()` and `_beforeRequest()` in `PhpCgiCloudflare` (`stubs/php.ts.stub`).

**Files modified:**
- `stubs/php.ts.stub` — source template (copied verbatim by `BuildDirectory::generatePhpTs()`)
- `playground/.laraworker/php.ts` — live playground copy

**Pattern established:**
1. `request()` override stores `request.headers` on `this._pendingHeaders`
2. `_beforeRequest()` override resolves `this.binary`, calls `php.ccall('wasm_sapi_cgi_putenv', ...)` for each header
3. Header name conversion: lowercase → `HTTP_` + uppercase + hyphens→underscores (e.g. `x-inertia` → `HTTP_X_INERTIA`)
4. Skips `host`, `cookie`, `content-type`, `content-length` (already handled by PhpCgiBase)
5. Clears stale headers from previous requests via `_previousHeaderEnvKeys` tracking (WASM instance is reused across requests)

**Gotchas:**
- `Headers.entries()` returns lowercase header names — skip-set comparison uses lowercase
- `_beforeRequest()` runs BEFORE PhpCgiBase sets its env vars in the `navigator.locks` callback, so our vars persist correctly
- Must clear previous request's headers to avoid stale `HTTP_X_INERTIA` persisting on non-Inertia follow-up requests
- `this.binary` is accessible from `_beforeRequest()` — it's a Promise that resolves to the same Emscripten module instance

### Task 2: Switch SESSION_DRIVER to cookie

Change `config/laraworker.php` env_overrides from `SESSION_DRIVER=array` to `SESSION_DRIVER=cookie`. The cookie driver stores session data in encrypted cookies — no server-side storage needed. PhpCgiBase's CookieJar already handles Set-Cookie persistence between requests within a Worker isolate.

### Task 3: Revert Link→a tag workaround and restore Inertia navigation

Revert commit 4497c3a changes in `playground/resources/js/Layouts/AppLayout.vue` — re-import `Link` from `@inertiajs/vue3` and replace `<a>` tags back to `<Link>` components.

### Task 4: End-to-end verification

Build and test: clicking Inertia Links should produce XHR requests (visible in Network tab), PHP should return JSON with `X-Inertia` header, and page transitions should be smooth without full reloads.