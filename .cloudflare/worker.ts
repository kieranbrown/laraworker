/**
 * Cloudflare Worker entry point for Laravel via php-cgi-wasm.
 *
 * Architecture for minimal CPU time per request:
 * - WASM instantiation happens at module scope (uses 1s startup budget)
 * - Filesystem setup (tar fetch/unpack) happens once on first request
 * - Subsequent requests reuse the cached PHP instance
 */

// Must be imported before any Emscripten module to shim document/window
import './shims';

import { PhpCgiCloudflare } from './php-custom';
import { untar } from './tar';

interface Env {
  ASSETS: Fetcher;
}

// Create PHP instance at module scope — WASM instantiation uses the 1s
// startup budget instead of per-request CPU time.
const php = new PhpCgiCloudflare({
  docroot: '/app/public',
  prefix: '/',
  entrypoint: 'index.php',
});

let filesystemReady: Promise<void> | null = null;

async function ensureFilesystem(env: Env): Promise<void> {
  if (!filesystemReady) {
    filesystemReady = initializeFilesystem(env);
  }
  await filesystemReady;
}

async function initializeFilesystem(env: Env): Promise<void> {
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

  // Ensure required Laravel directories exist and are writable
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
    FS.chmod(dir, 0o777);
  }

  // Write PHP stubs (iconv, mb_split, openssl) and php.ini
  writePhpStubs(FS);
}

function writePhpStubs(
  FS: {
    writeFile(path: string, data: string): void;
    analyzePath(p: string): { exists: boolean };
    mkdir(p: string): void;
  },
): void {
  FS.writeFile(
    '/app/iconv-stub.php',
    `<?php
// Emscripten MEMFS defaults to umask 0777 which breaks Laravel's Filesystem::replace()
// (it does chmod(0777 - umask()) → chmod(0) → permission denied). Set sane default.
umask(0022);

if (!function_exists('iconv')) {
    function iconv(string $from, string $to, string $string): string|false {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $to = preg_replace('/\\/\\/(TRANSLIT|IGNORE)/', '', $to);
        if (($from === 'UTF-8' || $from === 'UTF8') && ($to === 'UTF-8' || $to === 'UTF8' || $to === 'ASCII')) {
            if ($to === 'ASCII') {
                return preg_replace('/[\\x80-\\xFF]/', '', $string);
            }
            return $string;
        }
        if ($from === $to) {
            return $string;
        }
        return $string;
    }
}
if (!function_exists('iconv_strlen')) {
    function iconv_strlen(string $string, ?string $encoding = null): int|false {
        return strlen($string);
    }
}
if (!function_exists('iconv_strpos')) {
    function iconv_strpos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false {
        return strpos($haystack, $needle, $offset);
    }
}
if (!function_exists('iconv_strrpos')) {
    function iconv_strrpos(string $haystack, string $needle, ?string $encoding = null): int|false {
        return strrpos($haystack, $needle);
    }
}
if (!function_exists('iconv_substr')) {
    function iconv_substr(string $string, int $offset, ?int $length = null, ?string $encoding = null): string|false {
        return $length === null ? substr($string, $offset) : substr($string, $offset, $length);
    }
}
if (!function_exists('mb_split')) {
    function mb_split(string $pattern, string $string, int $limit = -1): array|false {
        return preg_split('/' . $pattern . '/u', $string, $limit) ?: false;
    }
}

if (!function_exists('openssl_cipher_iv_length')) {
    function openssl_cipher_iv_length(string $cipher_algo): int|false {
        $cipher = strtolower($cipher_algo);
        if (str_contains($cipher, 'aes') && str_contains($cipher, 'cbc')) return 16;
        if (str_contains($cipher, 'aes') && str_contains($cipher, 'gcm')) return 12;
        return 16;
    }
}
if (!function_exists('openssl_encrypt')) {
    function openssl_encrypt(string $data, string $cipher_algo, string $passphrase, int $options = 0, string $iv = '', &$tag = null, string $aad = '', int $tag_length = 16): string|false {
        $key = hash('sha256', $passphrase, true);
        $encrypted = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $encrypted .= $data[$i] ^ $key[$i % 32] ^ $iv[$i % strlen($iv)];
        }
        if ($tag !== null) {
            $tag = hash_hmac('sha256', $encrypted . $aad, $key, true);
            $tag = substr($tag, 0, $tag_length);
        }
        return ($options & OPENSSL_RAW_DATA) ? $encrypted : base64_encode($encrypted);
    }
}
if (!function_exists('openssl_decrypt')) {
    function openssl_decrypt(string $data, string $cipher_algo, string $passphrase, int $options = 0, string $iv = '', ?string $tag = null, string $aad = ''): string|false {
        $key = hash('sha256', $passphrase, true);
        $raw = ($options & OPENSSL_RAW_DATA) ? $data : base64_decode($data);
        if ($raw === false) return false;
        $decrypted = '';
        for ($i = 0; $i < strlen($raw); $i++) {
            $decrypted .= $raw[$i] ^ $key[$i % 32] ^ $iv[$i % strlen($iv)];
        }
        return $decrypted;
    }
}
if (!defined('OPENSSL_RAW_DATA')) define('OPENSSL_RAW_DATA', 1);
if (!defined('OPENSSL_CIPHER_AES_128_CBC')) define('OPENSSL_CIPHER_AES_128_CBC', 1);
if (!defined('OPENSSL_CIPHER_AES_256_CBC')) define('OPENSSL_CIPHER_AES_256_CBC', 2);
if (!function_exists('openssl_get_cipher_methods')) {
    function openssl_get_cipher_methods(bool $aliases = false): array {
        return ['aes-128-cbc', 'aes-256-cbc', 'aes-128-gcm', 'aes-256-gcm'];
    }
}
if (!function_exists('openssl_random_pseudo_bytes')) {
    function openssl_random_pseudo_bytes(int $length, &$strong_result = null): string {
        $strong_result = true;
        return random_bytes($length);
    }
}
`,
  );

  mkdirp(FS, '/config');
  FS.writeFile('/config/php.ini', 'auto_prepend_file=/app/iconv-stub.php\n');
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

      // Ensure filesystem is loaded (lazy — needs env.ASSETS)
      await ensureFilesystem(env);

      // Route through PHP
      return await php.request(request);
    } catch (error) {
      const message =
        error instanceof Error ? error.message : 'Unknown error';
      const stack = error instanceof Error ? error.stack : '';
      console.error('Worker error:', message, stack);
      return new Response(`Internal Server Error: ${message}`, {
        status: 500,
        headers: { 'Content-Type': 'text/plain' },
      });
    }
  },
};
