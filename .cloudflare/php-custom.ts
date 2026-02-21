/**
 * Custom PhpCgiBase subclass for Cloudflare Workers.
 *
 * Uses a custom-built PHP WASM binary with MAIN_MODULE=0 (static linking).
 * All extensions are baked into a single WASM file — no dynamic library
 * loading, no Emscripten patches needed.
 */

// @ts-expect-error — no type declarations for php-cgi-wasm internals
import { PhpCgiBase } from 'php-cgi-wasm/PhpCgiBase.mjs';
// @ts-expect-error — custom Emscripten module from php-wasm-builder
import PHP from './php-cgi-worker.mjs';

// Single WASM binary — Cloudflare pre-compiles on deploy
// @ts-expect-error — wasm import
import phpWasm from './php-cgi-worker.mjs.wasm';

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
          return { scriptName: '/index.php', path };
        }),
      autoTransaction: false,
      env: {
        ...env,
      },
      // Pass the pre-compiled WASM module directly.
      // With MAIN_MODULE=0, there are no shared libraries to load.
      instantiateWasm(
        info: WebAssembly.Imports,
        receiveInstance: (instance: WebAssembly.Instance, module?: WebAssembly.Module) => unknown,
      ) {
        const instance = new WebAssembly.Instance(phpWasm, info);
        return receiveInstance(instance, phpWasm);
      },
    });
  }

  async getFS() {
    const php = await this.binary;
    return php.FS;
  }
}
