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

      // Minimal debug endpoint
      if (url.pathname === '/__debug') {
        const FS = await instance.getFS();
        const dumps: string[] = [];

        // Check if compiled views exist
        try {
          const viewDir = '/app/storage/framework/views';
          if (FS.analyzePath(viewDir).exists) {
            const entries = FS.readdir(viewDir).filter((e: string) => e !== '.' && e !== '..');
            dumps.push(`Compiled views: ${entries.length} files`);
            entries.slice(0, 5).forEach((e: string) => dumps.push(`  - ${e}`));
          } else {
            dumps.push('Compiled views dir: NOT FOUND');
          }
        } catch {}

        // Check stubs file structure
        try {
          const stubs = FS.readFile('/app/php-stubs.php');
          const content = new TextDecoder().decode(stubs);
          dumps.push(`\nStubs file: ${content.length} bytes`);
          dumps.push(`Has namespace block: ${content.includes('namespace {')}`);
          dumps.push(`Has Illuminate\\View\\Compilers namespace: ${content.includes('namespace Illuminate\\View\\Compilers')}`);
        } catch (e: any) {
          dumps.push(`Stubs file error: ${e.message}`);
        }

        // Dump lines around 273 of the compiled welcome view
        try {
          const viewDir = '/app/storage/framework/views';
          const entries = FS.readdir(viewDir).filter((e: string) => e !== '.' && e !== '..' && e.endsWith('.php'));
          for (const entry of entries) {
            const bytes = FS.readFile(`${viewDir}/${entry}`);
            const content = new TextDecoder().decode(bytes);
            const lines = content.split('\n');
            if (lines.length > 250) { // likely the welcome view (273 lines)
              dumps.push(`\n=== ${entry} (${content.length} bytes, ${lines.length} lines) ===`);
              const start = Math.max(0, 268);
              const end = Math.min(lines.length, 278);
              for (let ln = start; ln < end; ln++) {
                dumps.push(`${ln + 1}: ${lines[ln].substring(0, 300)}`);
              }
              // Also show first 3 lines to check if it's valid compiled PHP
              dumps.push(`\nFirst 3 lines:`);
              for (let ln = 0; ln < Math.min(3, lines.length); ln++) {
                dumps.push(`${ln + 1}: ${lines[ln].substring(0, 300)}`);
              }
            }
          }
        } catch (e: any) {
          dumps.push(`View dump error: ${e.message}`);
        }

        // Trigger root request and capture stderr
        const rootReq = new Request('https://localhost/');
        const rootResp = await instance.request(rootReq);
        const rootBody = await rootResp.text();
        dumps.push(`\nRoot status: ${rootResp.status}`);
        dumps.push(`Root body (200 chars): ${rootBody.substring(0, 200)}`);

        // Check stderr for PHP errors
        const stderrBytes = (instance as any).error;
        const stderr = stderrBytes?.length
          ? new TextDecoder().decode(new Uint8Array(stderrBytes).buffer)
          : '(empty)';
        dumps.push(`\nPHP STDERR:\n${stderr.substring(0, 2000)}`);

        // Re-init MEMFS if refresh() wiped it
        if (!FS.analyzePath('/app/public/index.php').exists) {
          await initializeFilesystem(instance, env);
        }

        return new Response(dumps.join('\n'), {
          status: 200,
          headers: { 'Content-Type': 'text/plain' },
        });
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
