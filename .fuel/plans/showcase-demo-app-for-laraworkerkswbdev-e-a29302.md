# Showcase Demo App for laraworker.kswb.dev

## Goal
Transform the minimal playground into a visually impressive showcase demonstrating Laravel running on Cloudflare Workers via WebAssembly — with Inertia.js + Vue 3, SSR, performance metrics, and architecture explanations.

## Current State
- Single welcome.blade.php with inline CSS, no components
- Tailwind v4 + Vite 7 configured but barely used
- No Inertia, no Vue, no JS framework
- Inertia SSR infrastructure exists in the package (worker stub + inertia-ssr.ts) but is not wired up
- Static assets already served from CF edge (not Workers)
- OPcache working (~3x speedup on warm requests)

## Architecture Decisions
- **Vue 3** (default in laraworker config, most natural for Laravel ecosystem)
- **Inertia.js** with SSR enabled in the worker
- **Tailwind v4** for styling (already installed)
- **Server-Timing headers** added to worker for response time visibility
- SSR bundle built by Vite, copied into .laraworker/ for worker import

## Pages
1. **Home/Landing** — Hero section, key stats (PHP version, WASM, OPcache), feature cards
2. **Performance** — Live response timing via Server-Timing headers, OPcache hit/miss visualization, cold vs warm request comparison
3. **Architecture** — How it works: PHP WASM → CF Worker → static assets from edge, diagram-style layout
4. **Features** — What's supported: Blade, Inertia SSR, OPcache, static assets, Tailwind, etc.

## Task Breakdown

### 1. Install Inertia + Vue 3 in playground
- `composer require inertiajs/inertia-laravel` in playground/
- `bun add @inertiajs/vue3 vue @vue/server-renderer` in playground/
- Create root Blade template (app.blade.php) with @inertia directive
- Configure Inertia middleware in bootstrap/app.php
- Create resources/js/app.ts (Vue + Inertia client entry)
- Create resources/js/ssr.ts (SSR entry)
- Update vite.config.js for Vue + SSR build

### 2. Add Server-Timing headers to worker stub
- Measure PHP init time, request execution time, SSR time
- Add `Server-Timing` header to responses for browser DevTools visibility
- This is a package-level change to stubs/worker.ts.stub

### 3. Wire up Inertia SSR in build process ✅
- Update BuildCommand to run `vite build --ssr` when inertia.ssr is enabled
- Copy SSR bundle output to .laraworker/ssr/
- Update worker.ts.stub to conditionally import SSR bundle
- Enable INERTIA_SSR=true in playground config

**Implementation notes (task 3):**
- `BuildCommand::buildSsrBundle()` runs `npx vite build --ssr`, then copies `bootstrap/ssr/ssr.js` (or `.mjs`) to `.laraworker/ssr/ssr.js`
- `BuildDirectory::generateWorkerTs()` replaces `{{SSR_IMPORT}}` placeholder — when SSR enabled: `import _ssrRender from './ssr/ssr'; ssrRender = _ssrRender;`, when disabled: empty string
- The SSR entry (`playground/resources/js/ssr.ts`) uses `export default function render`, so worker uses default import
- `playground/package.json` has `build:ssr` script, `playground/.env` has `LARAWORKER_INERTIA_SSR=true`
- Non-SSR apps are unaffected — `buildSsrBundle()` returns early when `config('laraworker.inertia.ssr')` is false, and `{{SSR_IMPORT}}` is replaced with empty string

### 4. Build showcase Vue pages + controllers
- Create PageController with home/performance/architecture/features actions
- Create Vue page components for each page
- Create shared Layout component with navigation
- Wire up routes

### 5. Style with Tailwind v4
- Design a clean, modern landing page
- Dark mode support
- Responsive layout
- Code-style elements for the architecture page

### 6. Build, deploy, and verify
- Run build, test locally with wrangler dev
- Deploy to laraworker.kswb.dev
- Verify SSR works, timing headers show, all pages render

## Key Risks
- SSR bundle size might push worker over CF limits (need to verify)
- Vue SSR in the worker context (no DOM) — should work since renderToString doesn't need DOM
- Build process changes need to not break non-Inertia apps