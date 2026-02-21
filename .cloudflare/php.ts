/**
 * Custom PhpCgiBase subclass for Cloudflare Workers.
 *
 * - Extends PhpCgiBase directly (no navigator.locks from PhpCgiWebBase)
 * - Uses the simple FIFO queue from PhpCgiBase._enqueue()
 * - Disables IDBFS persistence (autoTransaction: false)
 * - Provides WASM binary via instantiateWasm for Workers compatibility
 */

// @ts-expect-error — no type declarations for php-cgi-wasm
import { PhpCgiBase } from 'php-cgi-wasm/PhpCgiBase.mjs';
// @ts-expect-error — patched Emscripten module (built by build-app.mjs)
import PHP from './php-cgi.mjs';

// Import WASM binary directly — Cloudflare Workers supports .wasm imports
// which produce a compiled WebAssembly.Module (no fetch needed)
// @ts-expect-error — wasm import
import phpWasm from '../node_modules/php-cgi-wasm/a6b86cfb60faf7f8d8e7143644223328da17002c.wasm';

// Import shared libraries as pre-compiled WebAssembly.Module objects.
// The build script copies .so → .wasm so Cloudflare pre-compiles them.
// @ts-expect-error — wasm import
import libxml2Module from './libxml2.wasm';
// @ts-expect-error — wasm import (mbstring dependency)
import libonigModule from './libonig.wasm';
// @ts-expect-error — wasm import
import mbstringModule from './php8.3-mbstring.wasm';
// @ts-expect-error — wasm import (openssl dependency)
import libcryptoModule from './libcrypto.wasm';
// @ts-expect-error — wasm import (openssl dependency)
import libsslModule from './libssl.wasm';
// @ts-expect-error — wasm import
import opensslModule from './php8.3-openssl.wasm';

interface PhpCgiCloudflareOptions {
  docroot?: string;
  prefix?: string;
  entrypoint?: string;
  rewrite?: (path: string) => string | { scriptName: string; path: string };
  env?: Record<string, string>;
}

export class PhpCgiCloudflare extends PhpCgiBase {
  constructor(options: PhpCgiCloudflareOptions = {}) {
    const {
      docroot = '/app/public',
      prefix = '/',
      entrypoint = 'index.php',
      rewrite,
      env = {},
    } = options;

    super(PHP, {
      docroot,
      prefix,
      entrypoint,
      rewrite:
        rewrite ??
        ((path: string) => {
          // Laravel-style rewrite: all non-file requests go through index.php
          return { scriptName: '/index.php', path };
        }),
      autoTransaction: false,
      // PHP extensions to register in php.ini (generates `extension=xxx.so`)
      // Their WASM modules are in _preloadedLibs so Emscripten can load them.
      sharedLibs: ['php8.3-mbstring.so', 'php8.3-openssl.so'],
      env: {
        ...env,
      },
      // Provide shared libraries as pre-compiled WebAssembly.Module objects.
      // The patched loadLibData returns these instead of fetching .so files.
      // loadWebAssemblyModule already handles `binary instanceof WebAssembly.Module`.
      _preloadedLibs: {
        'libxml2.so': libxml2Module,
        'libonig.so': libonigModule,
        'php8.3-mbstring.so': mbstringModule,
        'libcrypto.so': libcryptoModule,
        'libssl.so': libsslModule,
        'php8.3-openssl.so': opensslModule,
      },
      // Provide the WASM binary directly via instantiateWasm.
      // The imported .wasm module is pre-compiled by Cloudflare, so
      // synchronous instantiation is fast.
      instantiateWasm(
        info: WebAssembly.Imports,
        receiveInstance: (instance: WebAssembly.Instance, module?: WebAssembly.Module) => unknown,
      ) {
        const instance = new WebAssembly.Instance(phpWasm, info);
        return receiveInstance(instance, phpWasm);
      },
    });
  }

  /**
   * Get the Emscripten FS module once the PHP binary is ready.
   */
  async getFS() {
    const php = await this.binary;
    return php.FS;
  }
}
