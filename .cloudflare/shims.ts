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

