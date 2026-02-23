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
let initialized: Promise<void> | null = null;

async function ensureInitialized(env: Env): Promise<PhpCgiCloudflare> {
  if (!php) {
    php = new PhpCgiCloudflare({
      docroot: '/app/public',
      prefix: '/',
      entrypoint: 'index.php',
      ini: 'auto_prepend_file=/app/php-stubs.php',
    });

    initialized = initializeFilesystem(php, env);
  }

  await initialized;
  return php;
}

async function initializeFilesystem(
  php: PhpCgiCloudflare,
  env: Env,
): Promise<void> {
  const FS = await php.getFS();

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

    // Diagnostic: test PHP WASM initialization only
    if (url.pathname === '/__diag') {
      const steps: string[] = [];
      try {
        const t0 = Date.now();
        steps.push('Creating PhpCgiCloudflare...');
        const testPhp = new PhpCgiCloudflare({
          docroot: '/app/public',
          prefix: '/',
          entrypoint: 'index.php',
          ini: 'auto_prepend_file=/app/php-stubs.php',
        });
        steps.push(`Constructor done (${Date.now() - t0}ms)`);

        steps.push('Awaiting WASM binary (getFS)...');
        const t1 = Date.now();
        const FS = await testPhp.getFS();
        steps.push(`WASM ready (${Date.now() - t1}ms)`);

        steps.push('Fetching app.tar.gz...');
        const t2 = Date.now();
        const tarResponse = await env.ASSETS.fetch(new Request('http://assets.local/app.tar.gz'));
        steps.push(`Fetch done: ${tarResponse.status} (${Date.now() - t2}ms)`);

        if (tarResponse.ok) {
          steps.push('Decompressing...');
          const t3 = Date.now();
          const decompressed = tarResponse.body!.pipeThrough(new DecompressionStream('gzip'));
          const tarBuffer = await new Response(decompressed).arrayBuffer();
          steps.push(`Decompress done: ${(tarBuffer.byteLength / 1024 / 1024).toFixed(1)} MB (${Date.now() - t3}ms)`);

          steps.push('Untarring...');
          const t4 = Date.now();
          untar(FS, tarBuffer, '/app');
          steps.push(`Untar done (${Date.now() - t4}ms)`);
        }

        // Create required dirs
        const dirs = ['/app/storage/framework/sessions', '/app/storage/framework/views',
          '/app/storage/framework/cache', '/app/storage/framework/cache/data',
          '/app/storage/logs', '/app/bootstrap/cache'];
        for (const dir of dirs) { mkdirp(FS, dir); }
        steps.push('Dirs created');

        // Test PHP execution with a simple script
        steps.push('Testing PHP execution...');
        const t5 = Date.now();
        try {
          const phpResponse = await testPhp.request(
            new Request('https://laraworker.kswb.dev/', { method: 'GET' })
          );
          steps.push(`PHP done: ${phpResponse.status} (${Date.now() - t5}ms)`);
          const body = await phpResponse.text();
          steps.push(`Response length: ${body.length} chars`);
        } catch (phpErr) {
          const phpMsg = phpErr instanceof Error ? phpErr.message : String(phpErr);
          steps.push(`PHP error (${Date.now() - t5}ms): ${phpMsg}`);
        }

        steps.push(`Total: ${Date.now() - t0}ms`);
        return new Response(steps.join('\n'), { status: 200, headers: { 'Content-Type': 'text/plain' } });
      } catch (error) {
        const msg = error instanceof Error ? `${error.message}\n${error.stack}` : String(error);
        steps.push(`ERROR: ${msg}`);
        return new Response(steps.join('\n'), { status: 500, headers: { 'Content-Type': 'text/plain' } });
      }
    }

    try {
      const instance = await ensureInitialized(env);

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
