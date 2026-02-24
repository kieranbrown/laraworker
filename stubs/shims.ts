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
    createElement: () => ({}),
    body: { style: {}, appendChild: () => {}, scroll: 0 },
    documentElement: { style: {} },
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
