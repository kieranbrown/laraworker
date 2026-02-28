/**
 * Inertia SSR rendering for Cloudflare Workers.
 *
 * Intercepts HTML responses from PHP, extracts Inertia page data
 * from the `data-page` attribute, renders the page component via
 * Vue/React SSR (renderToString), and injects the rendered HTML
 * back into the response.
 *
 * This approach performs SSR inline within the same worker — no
 * separate SSR server or HTTP round-trip needed.
 */

/**
 * Result shape from Inertia's createInertiaApp SSR rendering.
 * `head` contains <title>, <meta>, etc. elements as strings.
 * `body` contains the full root div with rendered HTML inside.
 */
export interface InertiaSSRResult {
  head: string[];
  body: string;
}

/**
 * The render function that the user's SSR bundle must export.
 * Takes an Inertia page object and returns SSR'd head/body.
 *
 * This is typically wired up via createInertiaApp + renderToString.
 */
export type InertiaRenderFn = (page: Record<string, unknown>) => Promise<InertiaSSRResult>;

/**
 * Match the Inertia root div element in PHP-generated HTML.
 *
 * Laravel's @inertia Blade directive outputs (when SSR is NOT configured on PHP side):
 *   <div id="app" data-page="{&quot;component&quot;:&quot;...&quot;,...}"></div>
 *
 * The data-page value uses HTML entities because Blade's {{ }} escapes it.
 * We capture: [1] prefix up to value, [2] the encoded JSON value, [3] everything after.
 *
 * Note: `[^"']*` works because &quot; is not a literal quote character.
 */
const INERTIA_DIV_REGEX =
  /(<div\b[^>]*?\bid=["']app["'][^>]*?\bdata-page=["'])([^"']*)(["'][^>]*>)\s*<\/div>/;

/**
 * Attempt SSR rendering on an HTML string containing Inertia page data.
 *
 * Returns the modified HTML with SSR'd content injected, or null if:
 * - The HTML doesn't contain an Inertia root div
 * - The page data couldn't be parsed
 * - The render function threw an error (graceful CSR fallback)
 */
export async function renderInertiaSSR(
  html: string,
  render: InertiaRenderFn,
): Promise<string | null> {
  const match = html.match(INERTIA_DIV_REGEX);
  if (!match) {
    return null;
  }

  const encodedPageData = match[2];

  // Decode HTML entities from Blade's {{ }} escaping
  const pageJson = decodeHtmlEntities(encodedPageData);

  let page: Record<string, unknown>;
  try {
    page = JSON.parse(pageJson);
  } catch {
    console.warn("[Inertia SSR] Failed to parse page data JSON");
    return null;
  }

  // Validate this looks like Inertia page data
  if (typeof page.component !== "string" || !page.props) {
    return null;
  }

  let result: InertiaSSRResult;
  try {
    // Temporarily remove browser globals so Inertia's createInertiaApp detects
    // a server environment (it checks `typeof window === "undefined"`). The
    // shims.ts module defines window/document for Emscripten (PHP WASM), but
    // SSR rendering is pure JS and needs Inertia to take the server code path.
    const g = globalThis as Record<string, unknown>;
    const savedWindow = g.window;
    const savedDocument = g.document;
    // @ts-expect-error — intentional temporary removal for SSR detection
    delete globalThis.window;
    // @ts-expect-error — intentional temporary removal for SSR detection
    delete globalThis.document;
    try {
      result = await render(page);
    } finally {
      g.window = savedWindow;
      g.document = savedDocument;
    }
  } catch (error) {
    console.warn("[Inertia SSR] Render failed, falling back to CSR:", error);
    return null;
  }

  // Replace the empty root div with SSR'd body.
  // The body from createInertiaApp already includes the full
  // <div id="app" data-page="...">...rendered content...</div>
  let ssrHtml = html.replace(match[0], result.body);

  // Inject head elements (title, meta tags, etc.) before </head>
  if (result.head.length > 0) {
    const headContent = result.head.join("\n");
    ssrHtml = ssrHtml.replace("</head>", `${headContent}\n</head>`);
  }

  return ssrHtml;
}

function decodeHtmlEntities(str: string): string {
  const entities: Record<string, string> = {
    "&quot;": '"',
    "&#039;": "'",
    "&lt;": "<",
    "&gt;": ">",
    "&amp;": "&",
  };

  return str.replace(/&(?:quot|#039|lt|gt|amp);/g, (match) => entities[match] ?? match);
}
