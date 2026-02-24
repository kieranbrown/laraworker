/**
 * Cloudflare Worker entry point for Laravel via php-cgi-wasm.
 *
 * On cold start: initializes PHP, fetches app.tar from Static Assets,
 * unpacks into Emscripten MEMFS, and caches the PHP instance.
 * Each request is routed through php.request(request).
 *
 * Static assets are served by Cloudflare Static Assets (run_worker_first = false)
 * before the worker is invoked, so this worker only handles PHP routing.
 *
 * When Inertia SSR is enabled (INERTIA_SSR=true), HTML responses containing
 * Inertia page data are intercepted and server-side rendered inline using
 * the bundled SSR entry point. This eliminates the need for a separate
 * SSR server — the worker IS the SSR server.
 */

// Must be imported before any Emscripten module to shim document/window
import './shims';

import { PhpCgiCloudflare } from './php';
import { untar } from './tar';
import { renderInertiaSSR, type InertiaRenderFn } from './inertia-ssr';

/**
 * The SSR entry point — replace this import with your Vite-built SSR bundle.
 *
 * Your SSR entry should export a `render` function matching InertiaRenderFn:
 *   export async function render(page) { return createInertiaApp({ page, ... }) }
 *
 * Example Vite config for building the SSR bundle:
 *   build: { ssr: 'resources/js/ssr.ts', outDir: 'worker/ssr' }
 *
 * Then change this import to:
 *   import { render as ssrRender } from './ssr/ssr';
 */
let ssrRender: InertiaRenderFn | null = null;

// Uncomment and adjust the path when your SSR bundle is built:
// import { render as _ssrRender } from './ssr-bundle';
// ssrRender = _ssrRender;

interface Env {
  ASSETS: Fetcher;
  /**
   * Set to "true" to enable inline Inertia SSR rendering.
   * Requires an SSR bundle to be imported above.
   */
  INERTIA_SSR?: string;
}

let php: PhpCgiCloudflare | null = null;

async function ensureInitialized(env: Env): Promise<PhpCgiCloudflare> {
  if (!php) {
    php = new PhpCgiCloudflare({
      docroot: '/app/public',
      prefix: '/',
      entrypoint: 'index.php',
      // INITIAL_MEMORY is baked into the custom PHP 8.5 WASM binary (64 MB).
      // No need to set it here — the binary's memory import specifies min pages.
      ini: [
        'auto_prepend_file=/app/php-stubs.php',
        'opcache.enable=1',
        'opcache.enable_cli=1',
        'opcache.validate_timestamps=0',
        'opcache.memory_consumption=8',
        'opcache.interned_strings_buffer=4',
        'opcache.max_accelerated_files=2000',
      ].join('\n'),
    });
  }

  // PhpCgiBase.request() calls refresh() when PHP exits with non-zero code,
  // which creates a brand new Emscripten module with empty MEMFS. This wipes
  // all previously extracted files. Check if MEMFS needs (re-)initialization.
  const FS = await php.getFS();
  if (!FS.analyzePath('/app/public/index.php').exists) {
    await initializeFilesystem(php, env);
  }

  return php;
}

async function initializeFilesystem(
  php: PhpCgiCloudflare,
  env: Env,
): Promise<void> {
  const FS = await php.getFS();

  // Disable MEMFS permission enforcement entirely. Our tar unpacker doesn't
  // set file modes, so permissions depend on the Emscripten umask at creation
  // time. Since we're running in a WASM sandbox with no real users, there's
  // no security benefit to enforcing file permissions.
  FS.ignorePermissions = true;

  // Fetch the compressed Laravel application from Static Assets
  const tarResponse = await env.ASSETS.fetch(
    new Request('http://assets.local/app.tar.gz'),
  );

  if (!tarResponse.ok) {
    throw new Error(`Failed to fetch app.tar.gz: ${tarResponse.status}`);
  }

  // Decompress gzip using Web Streams API (available in Workers)
  const decompressed = tarResponse.body!.pipeThrough(
    new DecompressionStream('gzip'),
  );
  const tarBuffer = await new Response(decompressed).arrayBuffer();

  // Unpack into MEMFS at /app
  untar(FS, tarBuffer, '/app');

  // Ensure required Laravel directories exist
  const dirs = [
    '/app/storage/framework/sessions',
    '/app/storage/framework/views',
    '/app/storage/framework/cache',
    '/app/storage/framework/cache/data',
    '/app/storage/logs',
    '/app/bootstrap/cache',
  ];

  for (const dir of dirs) {
    mkdirp(FS, dir);
  }
}

function mkdirp(
  FS: { analyzePath(p: string): { exists: boolean }; mkdir(p: string): void },
  path: string,
): void {
  const parts = path.split('/').filter(Boolean);
  let current = '';
  for (const part of parts) {
    current += '/' + part;
    if (!FS.analyzePath(current).exists) {
      FS.mkdir(current);
    }
  }
}

/**
 * Attempt inline Inertia SSR on an HTML response from PHP.
 *
 * Flow:
 * 1. PHP generates HTML with <div id="app" data-page="..."></div>
 * 2. We extract the Inertia page data from data-page attribute
 * 3. We call renderToString via the SSR bundle to render the component
 * 4. We inject the rendered HTML + head elements back into the response
 * 5. Client-side Vue/React hydrates the pre-rendered DOM
 *
 * Falls back to client-side rendering (CSR) if SSR fails.
 */
async function maybeApplySSR(
  response: Response,
  env: Env,
): Promise<Response> {
  // Skip if SSR is not enabled or no render function available
  if (env.INERTIA_SSR !== 'true' || !ssrRender) {
    return response;
  }

  // Only process HTML responses (not JSON API responses, redirects, etc.)
  const contentType = response.headers.get('content-type') || '';
  if (!contentType.includes('text/html')) {
    return response;
  }

  // Don't SSR Inertia XHR responses (they're JSON, served directly)
  if (response.headers.has('x-inertia')) {
    return response;
  }

  const html = await response.text();
  const ssrHtml = await renderInertiaSSR(html, ssrRender);

  if (ssrHtml === null) {
    // SSR didn't apply (no Inertia data found, or render failed) — return original
    return new Response(html, {
      status: response.status,
      statusText: response.statusText,
      headers: response.headers,
    });
  }

  return new Response(ssrHtml, {
    status: response.status,
    statusText: response.statusText,
    headers: response.headers,
  });
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);

    // Intercept hashed Vite assets to add immutable cache headers
    // These are served from ASSETS but we need to add cache-control headers
    if (url.pathname.startsWith('/build/assets/')) {
      const assetResponse = await env.ASSETS.fetch(request);
      if (assetResponse.ok) {
        const headers = new Headers(assetResponse.headers);
        headers.set('Cache-Control', 'public, max-age=31536000, immutable');
        return new Response(assetResponse.body, {
          status: assetResponse.status,
          headers,
        });
      }
      return assetResponse;
    }

    // Health check — responds without initializing PHP
    if (url.pathname === '/__health') {
      return new Response('OK', { status: 200, headers: { 'Content-Type': 'text/plain' } });
    }

    try {
      const instance = await ensureInitialized(env);

      // Debug endpoint — PHP diagnostics via a temp PHP file
      if (url.pathname === '/__debug') {
        const FS = await instance.getFS();

        // Write a PHP diagnostic script to MEMFS
        const diagPhp = `<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Boot the application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\\Contracts\\Http\\Kernel::class);

echo "=== PHP DIAGNOSTICS ===\\n";
echo "PHP version: " . PHP_VERSION . "\\n";
echo "__DIR__ (diag): " . __DIR__ . "\\n";

// Check basePath
echo "app()->basePath(): " . $app->basePath() . "\\n";
echo "base_path(): " . base_path() . "\\n";

// Check PCRE settings
echo "\\n=== PCRE CONFIG ===\\n";
echo "pcre.backtrack_limit: " . ini_get('pcre.backtrack_limit') . "\\n";
echo "pcre.recursion_limit: " . ini_get('pcre.recursion_limit') . "\\n";
echo "pcre.jit: " . ini_get('pcre.jit') . "\\n";
echo "PCRE version: " . PCRE_VERSION . "\\n";

// Test PCRE on a large string with the Blade regex
echo "\\n=== PCRE REGEX TEST ===\\n";
$testStr = str_repeat("x", 80000) . "@if (true) hello @endif";
$result = preg_match_all('/\\\\B@(@?\\\\w+(?:::\\\\w+)?)([ \\\\t]*)(\\\\( ( [\\\\S\\\\s]*? ) \\\\))?/x', $testStr, $matches);
echo "preg_match_all result: " . var_export($result, true) . "\\n";
echo "preg_last_error: " . preg_last_error() . "\\n";
$errors = [0=>'NONE',1=>'INTERNAL',2=>'BACKTRACK_LIMIT',3=>'RECURSION_LIMIT',4=>'BAD_UTF8',5=>'BAD_UTF8_OFFSET',6=>'JIT_STACKLIMIT'];
echo "preg_last_error_msg: " . ($errors[preg_last_error()] ?? 'unknown') . "\\n";

// Test on actual welcome view content
echo "\\n=== BLADE COMPILE TEST ===\\n";
$welcomePath = '/app/resources/views/welcome.blade.php';
if (file_exists($welcomePath)) {
    $content = file_get_contents($welcomePath);
    echo "welcome.blade.php size: " . strlen($content) . "\\n";

    // Test the statement regex on the actual content
    $result2 = preg_match_all('/\\\\B@(@?\\\\w+(?:::\\\\w+)?)([ \\\\t]*)(\\\\( ( [\\\\S\\\\s]*? ) \\\\))?/x', $content, $matches2);
    echo "preg_match_all on welcome: " . var_export($result2, true) . "\\n";
    echo "preg_last_error: " . preg_last_error() . " (" . ($errors[preg_last_error()] ?? 'unknown') . ")\\n";
    if ($result2 !== false && $result2 > 0) {
        echo "Matches found: " . $result2 . "\\n";
        for ($i = 0; $i < min(5, $result2); $i++) {
            echo "  Match $i: " . substr($matches2[0][$i], 0, 80) . "\\n";
        }
    }

    // Test Blade compiler directly
    try {
        $compiler = $app->make('blade.compiler');
        echo "\\nBladeCompiler basePath: " . (new ReflectionProperty($compiler, 'basePath'))->getValue($compiler) . "\\n";
        echo "BladeCompiler cachePath: " . (new ReflectionProperty($compiler, 'cachePath'))->getValue($compiler) . "\\n";

        // Compile a small test
        $small = '@if(true) hello @endif {{ "world" }}';
        $compiled = $compiler->compileString($small);
        echo "Small test compiled: " . $compiled . "\\n";

        // Compile the first 500 chars of welcome
        $partial = substr($content, 0, 500);
        $compiledPartial = $compiler->compileString($partial);
        echo "Partial (500 chars) compiled: " . substr($compiledPartial, 0, 500) . "\\n";
    } catch (Throwable $e) {
        echo "Blade test error: " . $e->getMessage() . "\\n";
    }
} else {
    echo "welcome.blade.php not found\\n";
}

// Check view.compiled path from config
echo "\\n=== VIEW CONFIG ===\\n";
echo "view.compiled: " . config('view.compiled') . "\\n";
echo "view.paths: " . json_encode(config('view.paths')) . "\\n";
`;

        const enc = new TextEncoder();
        FS.writeFile('/app/public/__diag.php', enc.encode(diagPhp));

        const diagReq = new Request('https://localhost/__diag.php');
        const diagResp = await instance.request(diagReq);
        const diagBody = await diagResp.text();

        // Clean up
        try { FS.unlink('/app/public/__diag.php'); } catch {}

        // Re-init MEMFS if refresh() wiped it
        if (!FS.analyzePath('/app/public/index.php').exists) {
          await initializeFilesystem(instance, env);
        }

        return new Response(
          `Diag Status: ${diagResp.status}\n\n${diagBody}`,
          { status: 200, headers: { 'Content-Type': 'text/plain' } },
        );
      }

      // All requests go through PHP - static assets are served
      // by Cloudflare Static Assets before the worker is invoked
      const response = await instance.request(request);

      // Optionally apply Inertia SSR to HTML responses
      return await maybeApplySSR(response, env);
    } catch (error) {
      const message =
        error instanceof Error ? error.message : 'Unknown error';
      console.error('Worker error:', message);
      return new Response(`Internal Server Error: ${message}`, {
        status: 500,
        headers: { 'Content-Type': 'text/plain' },
      });
    }
  },
};
