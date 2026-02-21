/**
 * Cloudflare Worker entry point for Laravel via php-cgi-wasm.
 *
 * On cold start: initializes PHP, fetches app.tar from Static Assets,
 * unpacks into Emscripten MEMFS, and caches the PHP instance.
 * Each request is routed through php.request(request).
 */

// Must be imported before any Emscripten module to shim document/window
import './shims';

import { PhpCgiCloudflare } from './php';
import { untar } from './tar';

interface Env {
  ASSETS: Fetcher;
}

let php: PhpCgiCloudflare | null = null;
let initialized: Promise<void> | null = null;

async function ensureInitialized(env: Env): Promise<PhpCgiCloudflare> {
  if (!php) {
    php = new PhpCgiCloudflare({
      docroot: '/app/public',
      prefix: '/',
      entrypoint: 'index.php',
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

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    try {
      const instance = await ensureInitialized(env);

      const url = new URL(request.url);

      // Serve static assets from Vite build via Static Assets
      if (url.pathname.startsWith('/build/')) {
        const assetResponse = await env.ASSETS.fetch(request);
        if (assetResponse.ok) {
          return assetResponse;
        }
      }

      // Serve favicon and other public static files via Static Assets
      const staticExtensions = [
        '.ico',
        '.png',
        '.jpg',
        '.jpeg',
        '.gif',
        '.svg',
        '.css',
        '.js',
        '.woff',
        '.woff2',
        '.ttf',
      ];
      if (staticExtensions.some((ext) => url.pathname.endsWith(ext))) {
        const assetResponse = await env.ASSETS.fetch(request);
        if (assetResponse.ok) {
          return assetResponse;
        }
      }

      // Route everything else through PHP
      const response = await instance.request(request);

      return response;
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
