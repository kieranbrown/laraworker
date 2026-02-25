/**
 * Browser API shims for Emscripten compatibility in Cloudflare Workers.
 *
 * The Emscripten web build references `document` and `window` in fullscreen/canvas
 * code paths that are dead code in a Workers context. Without these shims, the
 * unguarded references would throw ReferenceError.
 *
 * This module MUST be imported before any Emscripten module.
 */

if (typeof globalThis.document === 'undefined') {
  // Minimal document shim. Key requirement: `document.currentScript` must be
  // falsy so Emscripten's scriptDirectory initialization takes the right path.
  // @ts-expect-error — minimal shim for Emscripten compatibility
  globalThis.document = {
    currentScript: null,
    getElementById: () => null,
    querySelector: () => null,
    querySelectorAll: () => [],
    createElement: () => ({
      style: {},
      appendChild: () => {},
      setAttribute: () => {},
      getAttribute: () => null,
      textContent: '',
      innerHTML: '',
    }),
    createTextNode: () => ({ textContent: '' }),
    body: { style: {}, appendChild: () => {}, scroll: 0 },
    documentElement: { style: {} },
    head: { appendChild: () => {}, removeChild: () => {} },
    fullscreenElement: null,
    fullscreenEnabled: false,
    addEventListener: () => {},
    removeEventListener: () => {},
  };
}

if (typeof globalThis.window === 'undefined') {
  // @ts-expect-error — minimal shim for Emscripten compatibility
  globalThis.window = globalThis;
}

// Inertia.js SSR reads `history.scrollRestoration` at import time.
// Workers don't have a History API — provide a minimal stub.
if (typeof globalThis.history === 'undefined') {
  // @ts-expect-error — minimal shim for Inertia SSR compatibility
  globalThis.history = {
    scrollRestoration: 'auto',
    pushState: () => {},
    replaceState: () => {},
    go: () => {},
    back: () => {},
    forward: () => {},
  };
}

// PhpCgiBase._request uses `new URL(globalThis.location)` for HTTP_HOST and
// REQUEST_SCHEME. In Cloudflare Workers module format, `self.location` is not
// defined. Provide a fallback — the actual host/scheme come from the request URL.
if (typeof globalThis.location === 'undefined') {
  // @ts-expect-error — minimal shim for PhpCgiBase compatibility
  globalThis.location = new URL('https://localhost');
}

// PhpCgiBase._request uses `navigator.userAgent` for SERVER_SOFTWARE.
if (typeof globalThis.navigator === 'undefined') {
  // @ts-expect-error — minimal shim for PhpCgiBase compatibility
  globalThis.navigator = { userAgent: 'Cloudflare-Workers' };
} else if (typeof globalThis.navigator.userAgent === 'undefined') {
  // @ts-expect-error — adding missing property
  globalThis.navigator.userAgent = 'Cloudflare-Workers';
}

// PhpCgiBase._request uses `navigator.locks.request()` for request serialization.
// Cloudflare Workers don't have the Web Locks API. Since WASM execution is
// single-threaded, we can safely shim it to just call the callback directly.
if (typeof navigator !== 'undefined' && !navigator.locks) {
  // @ts-expect-error — minimal shim for PhpCgiBase compatibility
  navigator.locks = {
    request: (_name: string, callback: () => unknown) => callback(),
  };
}

// Cloudflare Workers' global addEventListener() throws when useCapture is
// truthy. Emscripten calls addEventListener(type, handler, true) on `window`
// which resolves to globalThis. Since the worker uses ES module syntax (export
// default), the global addEventListener is unused — wrap it to force useCapture
// to false so Emscripten's calls succeed without error.
{
  const _real = globalThis.addEventListener.bind(globalThis);
  // @ts-expect-error — overriding global addEventListener signature
  globalThis.addEventListener = (
    type: string,
    listener: EventListenerOrEventListenerObject,
    options?: boolean | AddEventListenerOptions,
  ) => {
    const safe =
      typeof options === 'boolean' ? false : options ? { ...options, capture: false } : options;
    _real(type, listener, safe);
  };
}
