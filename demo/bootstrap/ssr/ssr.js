import {
  defineComponent,
  ref,
  mergeProps,
  unref,
  withCtx,
  createVNode,
  createTextVNode,
  toDisplayString,
  useSSRContext,
  openBlock,
  createBlock,
  Fragment,
  renderList,
  createCommentVNode,
  computed,
  createSSRApp,
  h,
} from "vue";
import {
  ssrRenderAttrs,
  ssrRenderComponent,
  ssrRenderList,
  ssrInterpolate,
  ssrRenderSlot,
  ssrRenderClass,
  ssrIncludeBooleanAttr,
  ssrRenderStyle,
} from "vue/server-renderer";
import { Link, Head, createInertiaApp } from "@inertiajs/vue3";
import { renderToString } from "@vue/server-renderer";
const _sfc_main$4 = /* @__PURE__ */ defineComponent({
  __name: "AppLayout",
  __ssrInlineRender: true,
  setup(__props) {
    const mobileMenuOpen = ref(false);
    const navLinks = [
      { name: "Home", route: "/" },
      { name: "Performance", route: "/performance" },
      { name: "Architecture", route: "/architecture" },
      { name: "Features", route: "/features" },
    ];
    return (_ctx, _push, _parent, _attrs) => {
      _push(
        `<div${ssrRenderAttrs(mergeProps({ class: "min-h-screen flex flex-col bg-gray-950 text-gray-100" }, _attrs))}><nav class="border-b border-gray-800 bg-gray-950/80 backdrop-blur-sm sticky top-0 z-50"><div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8"><div class="flex items-center justify-between h-16">`,
      );
      _push(
        ssrRenderComponent(
          unref(Link),
          {
            href: "/",
            class: "flex items-center gap-2 font-bold text-lg text-white",
          },
          {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(`<span class="text-orange-400"${_scopeId}>△</span> Laraworker `);
              } else {
                return [
                  createVNode("span", { class: "text-orange-400" }, "△"),
                  createTextVNode(" Laraworker "),
                ];
              }
            }),
            _: 1,
          },
          _parent,
        ),
      );
      _push(`<div class="hidden md:flex items-center gap-1"><!--[-->`);
      ssrRenderList(navLinks, (link) => {
        _push(
          ssrRenderComponent(
            unref(Link),
            {
              key: link.route,
              href: link.route,
              class:
                "px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/50 transition-colors",
            },
            {
              default: withCtx((_, _push2, _parent2, _scopeId) => {
                if (_push2) {
                  _push2(`${ssrInterpolate(link.name)}`);
                } else {
                  return [createTextVNode(toDisplayString(link.name), 1)];
                }
              }),
              _: 2,
            },
            _parent,
          ),
        );
      });
      _push(
        `<!--]--><a href="https://github.com/kieranbrown/laraworker" target="_blank" rel="noopener noreferrer" class="ml-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/50 transition-colors"> GitHub </a></div><button class="md:hidden p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800/50">`,
      );
      if (!mobileMenuOpen.value) {
        _push(
          `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>`,
        );
      } else {
        _push(
          `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`,
        );
      }
      _push(`</button></div>`);
      if (mobileMenuOpen.value) {
        _push(`<div class="md:hidden pb-4 border-t border-gray-800 mt-2 pt-2"><!--[-->`);
        ssrRenderList(navLinks, (link) => {
          _push(
            ssrRenderComponent(
              unref(Link),
              {
                key: link.route,
                href: link.route,
                class:
                  "block px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/50 transition-colors",
                onClick: ($event) => (mobileMenuOpen.value = false),
              },
              {
                default: withCtx((_, _push2, _parent2, _scopeId) => {
                  if (_push2) {
                    _push2(`${ssrInterpolate(link.name)}`);
                  } else {
                    return [createTextVNode(toDisplayString(link.name), 1)];
                  }
                }),
                _: 2,
              },
              _parent,
            ),
          );
        });
        _push(
          `<!--]--><a href="https://github.com/kieranbrown/laraworker" target="_blank" rel="noopener noreferrer" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/50 transition-colors"> GitHub </a></div>`,
        );
      } else {
        _push(`<!---->`);
      }
      _push(`</div></nav><main class="flex-1">`);
      ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
      _push(
        `</main><footer class="border-t border-gray-800 bg-gray-950"><div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8"><div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500"><div class="flex items-center gap-2"><span class="text-orange-400">△</span><span>Powered by <strong class="text-gray-400">Laraworker</strong></span></div><div> Laravel on Cloudflare Workers via PHP WebAssembly </div></div></div></footer></div>`,
      );
    };
  },
});
const _sfc_setup$4 = _sfc_main$4.setup;
_sfc_main$4.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add(
    "resources/js/Layouts/AppLayout.vue",
  );
  return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
const _sfc_main$3 = /* @__PURE__ */ defineComponent({
  __name: "Architecture",
  __ssrInlineRender: true,
  props: {
    phpVersion: {},
    extensions: {},
    sapi: {},
    opcacheEnabled: { type: Boolean },
    inertiaVersion: {},
  },
  setup(__props) {
    const layers = [
      {
        label: "Browser",
        color: "blue",
        description:
          "Your visitor's browser makes a request. Inertia handles client-side navigation after the first load.",
        runs: "Client",
      },
      {
        label: "Cloudflare Edge",
        color: "orange",
        description:
          "Static assets (CSS, JS, images) are served directly from Cloudflare's edge network — fast and cheap.",
        runs: "Edge (300+ locations)",
      },
      {
        label: "Cloudflare Worker",
        color: "amber",
        description:
          "Dynamic requests hit a Worker that initializes the PHP WASM runtime and executes your Laravel app.",
        runs: "Worker (V8 isolate)",
      },
      {
        label: "PHP WASM Runtime",
        color: "red",
        description:
          "Full PHP 8.5 compiled to WebAssembly. OPcache persists compiled opcodes across requests within the same isolate.",
        runs: "Inside Worker",
      },
      {
        label: "Laravel Framework",
        color: "rose",
        description:
          "Your complete Laravel application — routing, controllers, Blade, Eloquent, middleware — all running in WASM.",
        runs: "Inside PHP WASM",
      },
    ];
    const colorClasses = {
      blue: {
        border: "border-blue-500/30",
        bg: "bg-blue-500/10",
        text: "text-blue-400",
        dot: "bg-blue-400",
      },
      orange: {
        border: "border-orange-500/30",
        bg: "bg-orange-500/10",
        text: "text-orange-400",
        dot: "bg-orange-400",
      },
      amber: {
        border: "border-amber-500/30",
        bg: "bg-amber-500/10",
        text: "text-amber-400",
        dot: "bg-amber-400",
      },
      red: {
        border: "border-red-500/30",
        bg: "bg-red-500/10",
        text: "text-red-400",
        dot: "bg-red-400",
      },
      rose: {
        border: "border-rose-500/30",
        bg: "bg-rose-500/10",
        text: "text-rose-400",
        dot: "bg-rose-400",
      },
    };
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Architecture — Laraworker" }, null, _parent));
      _push(
        ssrRenderComponent(
          _sfc_main$4,
          null,
          {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(
                  `<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16"${_scopeId}><div class="text-center mb-12"${_scopeId}><h1 class="text-3xl sm:text-4xl font-bold text-white"${_scopeId}>Architecture</h1><p class="mt-3 text-gray-400 max-w-xl mx-auto"${_scopeId}> How a Laravel request flows from browser to response — entirely on Cloudflare&#39;s network. </p></div><div class="relative max-w-2xl mx-auto mb-16"${_scopeId}><!--[-->`,
                );
                ssrRenderList(layers, (layer, index) => {
                  _push2(`<div class="relative pl-10 pb-8 last:pb-0"${_scopeId}>`);
                  if (index < layers.length - 1) {
                    _push2(
                      `<div class="absolute left-4 top-6 bottom-0 w-px bg-gray-800"${_scopeId}></div>`,
                    );
                  } else {
                    _push2(`<!---->`);
                  }
                  _push2(
                    `<div class="${ssrRenderClass([colorClasses[layer.color].dot, "absolute left-2.5 top-1.5 w-3 h-3 rounded-full ring-4 ring-gray-950"])}"${_scopeId}></div><div class="${ssrRenderClass([[colorClasses[layer.color].border, colorClasses[layer.color].bg], "p-5 rounded-xl border"])}"${_scopeId}><div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-2"${_scopeId}><h3 class="text-lg font-semibold text-white"${_scopeId}>${ssrInterpolate(layer.label)}</h3><span class="text-xs font-mono px-2 py-0.5 rounded-full border border-gray-700 text-gray-400 w-fit"${_scopeId}>${ssrInterpolate(layer.runs)}</span></div><p class="text-sm text-gray-400 leading-relaxed"${_scopeId}>${ssrInterpolate(layer.description)}</p></div></div>`,
                  );
                });
                _push2(
                  `<!--]--></div><div class="grid grid-cols-1 md:grid-cols-2 gap-6"${_scopeId}><div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50"${_scopeId}><h3 class="text-lg font-semibold text-white mb-4"${_scopeId}>Runtime Details</h3><dl class="space-y-3 text-sm"${_scopeId}><div class="flex justify-between"${_scopeId}><dt class="text-gray-400"${_scopeId}>PHP Version</dt><dd class="text-white font-mono"${_scopeId}>${ssrInterpolate(__props.phpVersion)}</dd></div><div class="flex justify-between"${_scopeId}><dt class="text-gray-400"${_scopeId}>SAPI</dt><dd class="text-white font-mono"${_scopeId}>${ssrInterpolate(__props.sapi)}</dd></div><div class="flex justify-between"${_scopeId}><dt class="text-gray-400"${_scopeId}>OPcache</dt><dd class="${ssrRenderClass([__props.opcacheEnabled ? "text-green-400" : "text-gray-500", "font-mono"])}"${_scopeId}>${ssrInterpolate(__props.opcacheEnabled ? "Enabled" : "Disabled")}</dd></div><div class="flex justify-between"${_scopeId}><dt class="text-gray-400"${_scopeId}>Inertia</dt><dd class="text-white font-mono"${_scopeId}>${ssrInterpolate(__props.inertiaVersion)}</dd></div></dl></div><div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50"${_scopeId}><h3 class="text-lg font-semibold text-white mb-4"${_scopeId}> PHP Extensions <span class="text-sm font-normal text-gray-500"${_scopeId}>(${ssrInterpolate(__props.extensions.length)})</span></h3><div class="flex flex-wrap gap-2"${_scopeId}><!--[-->`,
                );
                ssrRenderList(__props.extensions, (ext) => {
                  _push2(
                    `<span class="px-2.5 py-1 rounded-lg bg-gray-800 text-xs font-mono text-gray-300"${_scopeId}>${ssrInterpolate(ext)}</span>`,
                  );
                });
                _push2(`<!--]--></div></div></div></div>`);
              } else {
                return [
                  createVNode("div", { class: "max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16" }, [
                    createVNode("div", { class: "text-center mb-12" }, [
                      createVNode(
                        "h1",
                        { class: "text-3xl sm:text-4xl font-bold text-white" },
                        "Architecture",
                      ),
                      createVNode(
                        "p",
                        { class: "mt-3 text-gray-400 max-w-xl mx-auto" },
                        " How a Laravel request flows from browser to response — entirely on Cloudflare's network. ",
                      ),
                    ]),
                    createVNode("div", { class: "relative max-w-2xl mx-auto mb-16" }, [
                      (openBlock(),
                      createBlock(
                        Fragment,
                        null,
                        renderList(layers, (layer, index) => {
                          return createVNode(
                            "div",
                            {
                              key: layer.label,
                              class: "relative pl-10 pb-8 last:pb-0",
                            },
                            [
                              index < layers.length - 1
                                ? (openBlock(),
                                  createBlock("div", {
                                    key: 0,
                                    class: "absolute left-4 top-6 bottom-0 w-px bg-gray-800",
                                  }))
                                : createCommentVNode("", true),
                              createVNode(
                                "div",
                                {
                                  class: [
                                    "absolute left-2.5 top-1.5 w-3 h-3 rounded-full ring-4 ring-gray-950",
                                    colorClasses[layer.color].dot,
                                  ],
                                },
                                null,
                                2,
                              ),
                              createVNode(
                                "div",
                                {
                                  class: [
                                    "p-5 rounded-xl border",
                                    [
                                      colorClasses[layer.color].border,
                                      colorClasses[layer.color].bg,
                                    ],
                                  ],
                                },
                                [
                                  createVNode(
                                    "div",
                                    {
                                      class:
                                        "flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-2",
                                    },
                                    [
                                      createVNode(
                                        "h3",
                                        { class: "text-lg font-semibold text-white" },
                                        toDisplayString(layer.label),
                                        1,
                                      ),
                                      createVNode(
                                        "span",
                                        {
                                          class:
                                            "text-xs font-mono px-2 py-0.5 rounded-full border border-gray-700 text-gray-400 w-fit",
                                        },
                                        toDisplayString(layer.runs),
                                        1,
                                      ),
                                    ],
                                  ),
                                  createVNode(
                                    "p",
                                    { class: "text-sm text-gray-400 leading-relaxed" },
                                    toDisplayString(layer.description),
                                    1,
                                  ),
                                ],
                                2,
                              ),
                            ],
                          );
                        }),
                        64,
                      )),
                    ]),
                    createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 gap-6" }, [
                      createVNode(
                        "div",
                        { class: "p-6 rounded-2xl border border-gray-800 bg-gray-900/50" },
                        [
                          createVNode(
                            "h3",
                            { class: "text-lg font-semibold text-white mb-4" },
                            "Runtime Details",
                          ),
                          createVNode("dl", { class: "space-y-3 text-sm" }, [
                            createVNode("div", { class: "flex justify-between" }, [
                              createVNode("dt", { class: "text-gray-400" }, "PHP Version"),
                              createVNode(
                                "dd",
                                { class: "text-white font-mono" },
                                toDisplayString(__props.phpVersion),
                                1,
                              ),
                            ]),
                            createVNode("div", { class: "flex justify-between" }, [
                              createVNode("dt", { class: "text-gray-400" }, "SAPI"),
                              createVNode(
                                "dd",
                                { class: "text-white font-mono" },
                                toDisplayString(__props.sapi),
                                1,
                              ),
                            ]),
                            createVNode("div", { class: "flex justify-between" }, [
                              createVNode("dt", { class: "text-gray-400" }, "OPcache"),
                              createVNode(
                                "dd",
                                {
                                  class: [
                                    __props.opcacheEnabled ? "text-green-400" : "text-gray-500",
                                    "font-mono",
                                  ],
                                },
                                toDisplayString(__props.opcacheEnabled ? "Enabled" : "Disabled"),
                                3,
                              ),
                            ]),
                            createVNode("div", { class: "flex justify-between" }, [
                              createVNode("dt", { class: "text-gray-400" }, "Inertia"),
                              createVNode(
                                "dd",
                                { class: "text-white font-mono" },
                                toDisplayString(__props.inertiaVersion),
                                1,
                              ),
                            ]),
                          ]),
                        ],
                      ),
                      createVNode(
                        "div",
                        { class: "p-6 rounded-2xl border border-gray-800 bg-gray-900/50" },
                        [
                          createVNode("h3", { class: "text-lg font-semibold text-white mb-4" }, [
                            createTextVNode(" PHP Extensions "),
                            createVNode(
                              "span",
                              { class: "text-sm font-normal text-gray-500" },
                              "(" + toDisplayString(__props.extensions.length) + ")",
                              1,
                            ),
                          ]),
                          createVNode("div", { class: "flex flex-wrap gap-2" }, [
                            (openBlock(true),
                            createBlock(
                              Fragment,
                              null,
                              renderList(__props.extensions, (ext) => {
                                return (
                                  openBlock(),
                                  createBlock(
                                    "span",
                                    {
                                      key: ext,
                                      class:
                                        "px-2.5 py-1 rounded-lg bg-gray-800 text-xs font-mono text-gray-300",
                                    },
                                    toDisplayString(ext),
                                    1,
                                  )
                                );
                              }),
                              128,
                            )),
                          ]),
                        ],
                      ),
                    ]),
                  ]),
                ];
              }
            }),
            _: 1,
          },
          _parent,
        ),
      );
      _push(`<!--]-->`);
    };
  },
});
const _sfc_setup$3 = _sfc_main$3.setup;
_sfc_main$3.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add(
    "resources/js/Pages/Architecture.vue",
  );
  return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
const __vite_glob_0_0 = /* @__PURE__ */ Object.freeze(
  /* @__PURE__ */ Object.defineProperty(
    {
      __proto__: null,
      default: _sfc_main$3,
    },
    Symbol.toStringTag,
    { value: "Module" },
  ),
);
const _sfc_main$2 = /* @__PURE__ */ defineComponent({
  __name: "Features",
  __ssrInlineRender: true,
  props: {
    features: {},
  },
  setup(__props) {
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Features — Laraworker" }, null, _parent));
      _push(
        ssrRenderComponent(
          _sfc_main$4,
          null,
          {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(
                  `<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16"${_scopeId}><div class="text-center mb-12"${_scopeId}><h1 class="text-3xl sm:text-4xl font-bold text-white"${_scopeId}>Features</h1><p class="mt-3 text-gray-400 max-w-xl mx-auto"${_scopeId}> What&#39;s supported when running Laravel on Cloudflare Workers with Laraworker. </p></div><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"${_scopeId}><!--[-->`,
                );
                ssrRenderList(__props.features, (feature) => {
                  _push2(
                    `<div class="${ssrRenderClass([feature.status === "supported" ? "border-gray-800 hover:border-gray-700" : "border-gray-800/50 opacity-75", "p-6 rounded-2xl border bg-gray-900/50 transition-colors"])}"${_scopeId}><div class="flex items-start justify-between gap-3 mb-3"${_scopeId}><h3 class="text-lg font-semibold text-white"${_scopeId}>${ssrInterpolate(feature.name)}</h3><span class="${ssrRenderClass([feature.status === "supported" ? "bg-green-500/10 text-green-400 border border-green-500/20" : "bg-yellow-500/10 text-yellow-400 border border-yellow-500/20", "shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium"])}"${_scopeId}>${ssrInterpolate(feature.status === "supported" ? "Supported" : "Coming Soon")}</span></div><p class="text-sm text-gray-400 leading-relaxed"${_scopeId}>${ssrInterpolate(feature.description)}</p></div>`,
                  );
                });
                _push2(`<!--]--></div></div>`);
              } else {
                return [
                  createVNode("div", { class: "max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16" }, [
                    createVNode("div", { class: "text-center mb-12" }, [
                      createVNode(
                        "h1",
                        { class: "text-3xl sm:text-4xl font-bold text-white" },
                        "Features",
                      ),
                      createVNode(
                        "p",
                        { class: "mt-3 text-gray-400 max-w-xl mx-auto" },
                        " What's supported when running Laravel on Cloudflare Workers with Laraworker. ",
                      ),
                    ]),
                    createVNode(
                      "div",
                      { class: "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" },
                      [
                        (openBlock(true),
                        createBlock(
                          Fragment,
                          null,
                          renderList(__props.features, (feature) => {
                            return (
                              openBlock(),
                              createBlock(
                                "div",
                                {
                                  key: feature.name,
                                  class: [
                                    "p-6 rounded-2xl border bg-gray-900/50 transition-colors",
                                    feature.status === "supported"
                                      ? "border-gray-800 hover:border-gray-700"
                                      : "border-gray-800/50 opacity-75",
                                  ],
                                },
                                [
                                  createVNode(
                                    "div",
                                    { class: "flex items-start justify-between gap-3 mb-3" },
                                    [
                                      createVNode(
                                        "h3",
                                        { class: "text-lg font-semibold text-white" },
                                        toDisplayString(feature.name),
                                        1,
                                      ),
                                      createVNode(
                                        "span",
                                        {
                                          class: [
                                            "shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium",
                                            feature.status === "supported"
                                              ? "bg-green-500/10 text-green-400 border border-green-500/20"
                                              : "bg-yellow-500/10 text-yellow-400 border border-yellow-500/20",
                                          ],
                                        },
                                        toDisplayString(
                                          feature.status === "supported"
                                            ? "Supported"
                                            : "Coming Soon",
                                        ),
                                        3,
                                      ),
                                    ],
                                  ),
                                  createVNode(
                                    "p",
                                    { class: "text-sm text-gray-400 leading-relaxed" },
                                    toDisplayString(feature.description),
                                    1,
                                  ),
                                ],
                                2,
                              )
                            );
                          }),
                          128,
                        )),
                      ],
                    ),
                  ]),
                ];
              }
            }),
            _: 1,
          },
          _parent,
        ),
      );
      _push(`<!--]-->`);
    };
  },
});
const _sfc_setup$2 = _sfc_main$2.setup;
_sfc_main$2.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add(
    "resources/js/Pages/Features.vue",
  );
  return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
const __vite_glob_0_1 = /* @__PURE__ */ Object.freeze(
  /* @__PURE__ */ Object.defineProperty(
    {
      __proto__: null,
      default: _sfc_main$2,
    },
    Symbol.toStringTag,
    { value: "Module" },
  ),
);
const _sfc_main$1 = /* @__PURE__ */ defineComponent({
  __name: "Home",
  __ssrInlineRender: true,
  props: {
    phpVersion: {},
    serverInfo: {},
    laravelVersion: {},
    opcacheEnabled: { type: Boolean },
    opcacheStats: {},
  },
  setup(__props) {
    const featureCards = [
      {
        title: "Inertia SSR",
        description:
          "Server-side rendered Vue pages built in the worker for instant loads and SEO.",
        icon: "&#9889;",
      },
      {
        title: "OPcache",
        description:
          "Persistent opcode cache across requests — warm responses are up to 3x faster.",
        icon: "&#128640;",
      },
      {
        title: "Edge Assets",
        description: "Static files served from Cloudflare edge, never touching Workers.",
        icon: "&#127760;",
      },
      {
        title: "Full Laravel",
        description: "Eloquent, Blade, queues, routing — the complete Laravel framework.",
        icon: "&#9881;",
      },
    ];
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(
        ssrRenderComponent(
          unref(Head),
          { title: "Laraworker — Laravel on Cloudflare Workers" },
          null,
          _parent,
        ),
      );
      _push(
        ssrRenderComponent(
          _sfc_main$4,
          null,
          {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(
                  `<section class="relative overflow-hidden"${_scopeId}><div class="absolute inset-0 bg-gradient-to-b from-orange-500/5 to-transparent pointer-events-none"${_scopeId}></div><div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32 relative"${_scopeId}><div class="text-center max-w-3xl mx-auto"${_scopeId}><h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white"${_scopeId}> Laravel on <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-amber-300"${_scopeId}> Cloudflare Workers </span></h1><p class="mt-6 text-lg sm:text-xl text-gray-400 leading-relaxed"${_scopeId}> Run your full Laravel application at the edge — powered by PHP compiled to WebAssembly. No containers, no cold starts from VMs, just your code running globally. </p><div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4"${_scopeId}><a href="https://github.com/kieranbrown/laraworker" target="_blank" rel="noopener noreferrer" class="px-6 py-3 rounded-xl bg-orange-500 hover:bg-orange-400 text-white font-semibold transition-colors"${_scopeId}> View on GitHub </a><a href="/performance" class="px-6 py-3 rounded-xl border border-gray-700 hover:border-gray-500 text-gray-300 hover:text-white font-semibold transition-colors"${_scopeId}> See Performance </a></div></div></div></section><section class="border-y border-gray-800 bg-gray-900/50"${_scopeId}><div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6"${_scopeId}><div class="grid grid-cols-2 lg:grid-cols-4 gap-6 text-center"${_scopeId}><div${_scopeId}><div class="text-sm text-gray-500 uppercase tracking-wider"${_scopeId}>PHP Version</div><div class="mt-1 text-xl font-bold text-white"${_scopeId}>${ssrInterpolate(__props.phpVersion)}</div></div><div${_scopeId}><div class="text-sm text-gray-500 uppercase tracking-wider"${_scopeId}>Laravel</div><div class="mt-1 text-xl font-bold text-white"${_scopeId}>v${ssrInterpolate(__props.laravelVersion)}</div></div><div${_scopeId}><div class="text-sm text-gray-500 uppercase tracking-wider"${_scopeId}>Runtime</div><div class="mt-1 text-xl font-bold text-orange-400"${_scopeId}>WebAssembly</div></div><div${_scopeId}><div class="text-sm text-gray-500 uppercase tracking-wider"${_scopeId}>OPcache</div><div class="${ssrRenderClass([__props.opcacheEnabled ? "text-green-400" : "text-gray-500", "mt-1 text-xl font-bold"])}"${_scopeId}>${ssrInterpolate(__props.opcacheEnabled ? "Enabled" : "Disabled")}</div></div></div></div></section><section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20"${_scopeId}><h2 class="text-2xl sm:text-3xl font-bold text-white text-center"${_scopeId}>How It Works</h2><p class="mt-3 text-gray-400 text-center max-w-xl mx-auto"${_scopeId}> Laraworker compiles PHP to WebAssembly and runs your Laravel app inside Cloudflare Workers. </p><div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"${_scopeId}><!--[-->`,
                );
                ssrRenderList(featureCards, (card) => {
                  _push2(
                    `<div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50 hover:border-gray-700 transition-colors"${_scopeId}><div class="text-3xl mb-4"${_scopeId}>${card.icon ?? ""}</div><h3 class="text-lg font-semibold text-white"${_scopeId}>${ssrInterpolate(card.title)}</h3><p class="mt-2 text-sm text-gray-400 leading-relaxed"${_scopeId}>${ssrInterpolate(card.description)}</p></div>`,
                  );
                });
                _push2(
                  `<!--]--></div></section><section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-20"${_scopeId}><div class="rounded-2xl border border-gray-800 bg-gradient-to-r from-orange-500/10 to-amber-500/10 p-8 sm:p-12 text-center"${_scopeId}><h2 class="text-2xl sm:text-3xl font-bold text-white"${_scopeId}>Ready to deploy Laravel at the edge?</h2><p class="mt-3 text-gray-400 max-w-lg mx-auto"${_scopeId}> Get started with Laraworker and run your Laravel application on Cloudflare&#39;s global network. </p><a href="https://github.com/kieranbrown/laraworker" target="_blank" rel="noopener noreferrer" class="mt-6 inline-block px-6 py-3 rounded-xl bg-orange-500 hover:bg-orange-400 text-white font-semibold transition-colors"${_scopeId}> Get Started on GitHub </a></div></section>`,
                );
              } else {
                return [
                  createVNode("section", { class: "relative overflow-hidden" }, [
                    createVNode("div", {
                      class:
                        "absolute inset-0 bg-gradient-to-b from-orange-500/5 to-transparent pointer-events-none",
                    }),
                    createVNode(
                      "div",
                      { class: "max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32 relative" },
                      [
                        createVNode("div", { class: "text-center max-w-3xl mx-auto" }, [
                          createVNode(
                            "h1",
                            {
                              class:
                                "text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white",
                            },
                            [
                              createTextVNode(" Laravel on "),
                              createVNode(
                                "span",
                                {
                                  class:
                                    "text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-amber-300",
                                },
                                " Cloudflare Workers ",
                              ),
                            ],
                          ),
                          createVNode(
                            "p",
                            { class: "mt-6 text-lg sm:text-xl text-gray-400 leading-relaxed" },
                            " Run your full Laravel application at the edge — powered by PHP compiled to WebAssembly. No containers, no cold starts from VMs, just your code running globally. ",
                          ),
                          createVNode(
                            "div",
                            {
                              class:
                                "mt-10 flex flex-col sm:flex-row items-center justify-center gap-4",
                            },
                            [
                              createVNode(
                                "a",
                                {
                                  href: "https://github.com/kieranbrown/laraworker",
                                  target: "_blank",
                                  rel: "noopener noreferrer",
                                  class:
                                    "px-6 py-3 rounded-xl bg-orange-500 hover:bg-orange-400 text-white font-semibold transition-colors",
                                },
                                " View on GitHub ",
                              ),
                              createVNode(
                                "a",
                                {
                                  href: "/performance",
                                  class:
                                    "px-6 py-3 rounded-xl border border-gray-700 hover:border-gray-500 text-gray-300 hover:text-white font-semibold transition-colors",
                                },
                                " See Performance ",
                              ),
                            ],
                          ),
                        ]),
                      ],
                    ),
                  ]),
                  createVNode("section", { class: "border-y border-gray-800 bg-gray-900/50" }, [
                    createVNode("div", { class: "max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6" }, [
                      createVNode(
                        "div",
                        { class: "grid grid-cols-2 lg:grid-cols-4 gap-6 text-center" },
                        [
                          createVNode("div", null, [
                            createVNode(
                              "div",
                              { class: "text-sm text-gray-500 uppercase tracking-wider" },
                              "PHP Version",
                            ),
                            createVNode(
                              "div",
                              { class: "mt-1 text-xl font-bold text-white" },
                              toDisplayString(__props.phpVersion),
                              1,
                            ),
                          ]),
                          createVNode("div", null, [
                            createVNode(
                              "div",
                              { class: "text-sm text-gray-500 uppercase tracking-wider" },
                              "Laravel",
                            ),
                            createVNode(
                              "div",
                              { class: "mt-1 text-xl font-bold text-white" },
                              "v" + toDisplayString(__props.laravelVersion),
                              1,
                            ),
                          ]),
                          createVNode("div", null, [
                            createVNode(
                              "div",
                              { class: "text-sm text-gray-500 uppercase tracking-wider" },
                              "Runtime",
                            ),
                            createVNode(
                              "div",
                              { class: "mt-1 text-xl font-bold text-orange-400" },
                              "WebAssembly",
                            ),
                          ]),
                          createVNode("div", null, [
                            createVNode(
                              "div",
                              { class: "text-sm text-gray-500 uppercase tracking-wider" },
                              "OPcache",
                            ),
                            createVNode(
                              "div",
                              {
                                class: [
                                  "mt-1 text-xl font-bold",
                                  __props.opcacheEnabled ? "text-green-400" : "text-gray-500",
                                ],
                              },
                              toDisplayString(__props.opcacheEnabled ? "Enabled" : "Disabled"),
                              3,
                            ),
                          ]),
                        ],
                      ),
                    ]),
                  ]),
                  createVNode(
                    "section",
                    { class: "max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20" },
                    [
                      createVNode(
                        "h2",
                        { class: "text-2xl sm:text-3xl font-bold text-white text-center" },
                        "How It Works",
                      ),
                      createVNode(
                        "p",
                        { class: "mt-3 text-gray-400 text-center max-w-xl mx-auto" },
                        " Laraworker compiles PHP to WebAssembly and runs your Laravel app inside Cloudflare Workers. ",
                      ),
                      createVNode(
                        "div",
                        { class: "mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6" },
                        [
                          (openBlock(),
                          createBlock(
                            Fragment,
                            null,
                            renderList(featureCards, (card) => {
                              return createVNode(
                                "div",
                                {
                                  key: card.title,
                                  class:
                                    "p-6 rounded-2xl border border-gray-800 bg-gray-900/50 hover:border-gray-700 transition-colors",
                                },
                                [
                                  createVNode(
                                    "div",
                                    {
                                      class: "text-3xl mb-4",
                                      innerHTML: card.icon,
                                    },
                                    null,
                                    8,
                                    ["innerHTML"],
                                  ),
                                  createVNode(
                                    "h3",
                                    { class: "text-lg font-semibold text-white" },
                                    toDisplayString(card.title),
                                    1,
                                  ),
                                  createVNode(
                                    "p",
                                    { class: "mt-2 text-sm text-gray-400 leading-relaxed" },
                                    toDisplayString(card.description),
                                    1,
                                  ),
                                ],
                              );
                            }),
                            64,
                          )),
                        ],
                      ),
                    ],
                  ),
                  createVNode(
                    "section",
                    { class: "max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-20" },
                    [
                      createVNode(
                        "div",
                        {
                          class:
                            "rounded-2xl border border-gray-800 bg-gradient-to-r from-orange-500/10 to-amber-500/10 p-8 sm:p-12 text-center",
                        },
                        [
                          createVNode(
                            "h2",
                            { class: "text-2xl sm:text-3xl font-bold text-white" },
                            "Ready to deploy Laravel at the edge?",
                          ),
                          createVNode(
                            "p",
                            { class: "mt-3 text-gray-400 max-w-lg mx-auto" },
                            " Get started with Laraworker and run your Laravel application on Cloudflare's global network. ",
                          ),
                          createVNode(
                            "a",
                            {
                              href: "https://github.com/kieranbrown/laraworker",
                              target: "_blank",
                              rel: "noopener noreferrer",
                              class:
                                "mt-6 inline-block px-6 py-3 rounded-xl bg-orange-500 hover:bg-orange-400 text-white font-semibold transition-colors",
                            },
                            " Get Started on GitHub ",
                          ),
                        ],
                      ),
                    ],
                  ),
                ];
              }
            }),
            _: 1,
          },
          _parent,
        ),
      );
      _push(`<!--]-->`);
    };
  },
});
const _sfc_setup$1 = _sfc_main$1.setup;
_sfc_main$1.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add(
    "resources/js/Pages/Home.vue",
  );
  return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
const __vite_glob_0_2 = /* @__PURE__ */ Object.freeze(
  /* @__PURE__ */ Object.defineProperty(
    {
      __proto__: null,
      default: _sfc_main$1,
    },
    Symbol.toStringTag,
    { value: "Module" },
  ),
);
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "Performance",
  __ssrInlineRender: true,
  props: {
    phpVersion: {},
    opcacheEnabled: { type: Boolean },
    opcacheStats: {},
    memoryUsage: {},
  },
  setup(__props) {
    const props = __props;
    const latency = ref(null);
    const measuring = ref(false);
    const measurements = ref([]);
    const opcacheStats = ref({ ...props.opcacheStats });
    const memoryUsage = ref({ ...props.memoryUsage });
    const opcacheEnabled = ref(props.opcacheEnabled);
    function formatBytes(bytes) {
      if (bytes === 0) return "0 B";
      const units = ["B", "KB", "MB", "GB"];
      const i = Math.floor(Math.log(bytes) / Math.log(1024));
      return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
    }
    async function measureLatency() {
      measuring.value = true;
      const start = performance.now();
      try {
        const response = await fetch("/performance", {
          headers: { "X-Inertia": "true", "X-Inertia-Version": "" },
        });
        const data = await response.json();
        if (data?.props) {
          opcacheEnabled.value = data.props.opcacheEnabled;
          opcacheStats.value = { ...data.props.opcacheStats };
          memoryUsage.value = { ...data.props.memoryUsage };
        }
      } catch {}
      const elapsed = Math.round(performance.now() - start);
      latency.value = elapsed;
      measurements.value.push(elapsed);
      measuring.value = false;
    }
    const totalMemory = computed(
      () => memoryUsage.value.usedMemory + memoryUsage.value.freeMemory || 1,
    );
    const memoryPercent = computed(() =>
      Math.round((memoryUsage.value.usedMemory / totalMemory.value) * 100),
    );
    const totalRequests = computed(() => opcacheStats.value.hits + opcacheStats.value.misses || 1);
    const hitPercent = computed(() =>
      Math.round((opcacheStats.value.hits / totalRequests.value) * 100),
    );
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<!--[-->`);
      _push(ssrRenderComponent(unref(Head), { title: "Performance — Laraworker" }, null, _parent));
      _push(
        ssrRenderComponent(
          _sfc_main$4,
          null,
          {
            default: withCtx((_, _push2, _parent2, _scopeId) => {
              if (_push2) {
                _push2(
                  `<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16"${_scopeId}><div class="text-center mb-12"${_scopeId}><h1 class="text-3xl sm:text-4xl font-bold text-white"${_scopeId}>Performance</h1><p class="mt-3 text-gray-400 max-w-xl mx-auto"${_scopeId}> Real metrics from this running instance. OPcache keeps warm requests fast. </p></div><div class="mb-12 p-6 rounded-2xl border border-gray-800 bg-gray-900/50"${_scopeId}><div class="flex flex-col sm:flex-row items-center justify-between gap-4"${_scopeId}><div${_scopeId}><h2 class="text-lg font-semibold text-white"${_scopeId}>Client-Side Latency Test</h2><p class="text-sm text-gray-400 mt-1"${_scopeId}>Measure round-trip time from your browser to this worker.</p></div><button class="px-5 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-400 text-white font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"${ssrIncludeBooleanAttr(measuring.value) ? " disabled" : ""}${_scopeId}>${ssrInterpolate(measuring.value ? "Measuring..." : "Try It")}</button></div>`,
                );
                if (latency.value !== null) {
                  _push2(
                    `<div class="mt-6 flex flex-wrap items-end gap-6"${_scopeId}><div${_scopeId}><div class="text-sm text-gray-500 uppercase tracking-wider"${_scopeId}>Last Request</div><div class="text-3xl font-bold text-white"${_scopeId}>${ssrInterpolate(latency.value)}<span class="text-lg text-gray-400"${_scopeId}>ms</span></div></div>`,
                  );
                  if (measurements.value.length > 1) {
                    _push2(
                      `<div${_scopeId}><div class="text-sm text-gray-500 uppercase tracking-wider"${_scopeId}>Average (${ssrInterpolate(measurements.value.length)} reqs)</div><div class="text-3xl font-bold text-white"${_scopeId}>${ssrInterpolate(Math.round(measurements.value.reduce((a, b) => a + b, 0) / measurements.value.length))}<span class="text-lg text-gray-400"${_scopeId}>ms</span></div></div>`,
                    );
                  } else {
                    _push2(`<!---->`);
                  }
                  if (measurements.value.length >= 2) {
                    _push2(
                      `<div class="text-sm text-gray-500"${_scopeId}> First request (cold): <span class="text-gray-300"${_scopeId}>${ssrInterpolate(measurements.value[0])}ms</span> · Latest (warm): <span class="text-gray-300"${_scopeId}>${ssrInterpolate(measurements.value[measurements.value.length - 1])}ms</span></div>`,
                    );
                  } else {
                    _push2(`<!---->`);
                  }
                  _push2(`</div>`);
                } else {
                  _push2(`<!---->`);
                }
                _push2(
                  `</div><div class="grid grid-cols-1 md:grid-cols-2 gap-6"${_scopeId}><div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50"${_scopeId}><h3 class="text-lg font-semibold text-white mb-4"${_scopeId}>OPcache Hit Rate</h3><div class="flex items-end gap-3 mb-4"${_scopeId}><span class="${ssrRenderClass([opcacheEnabled.value ? "text-green-400" : "text-gray-500", "text-4xl font-bold"])}"${_scopeId}>${ssrInterpolate(opcacheEnabled.value ? `${hitPercent.value}%` : "Off")}</span></div>`,
                );
                if (opcacheEnabled.value) {
                  _push2(
                    `<div class="space-y-3"${_scopeId}><div class="w-full bg-gray-800 rounded-full h-2.5"${_scopeId}><div class="bg-green-500 h-2.5 rounded-full transition-all" style="${ssrRenderStyle({ width: hitPercent.value + "%" })}"${_scopeId}></div></div><div class="flex justify-between text-sm text-gray-400"${_scopeId}><span${_scopeId}>${ssrInterpolate(opcacheStats.value.hits.toLocaleString())} hits</span><span${_scopeId}>${ssrInterpolate(opcacheStats.value.misses.toLocaleString())} misses</span></div></div>`,
                  );
                } else {
                  _push2(
                    `<p class="text-sm text-gray-500"${_scopeId}>OPcache is not enabled on this instance.</p>`,
                  );
                }
                _push2(
                  `</div><div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50"${_scopeId}><h3 class="text-lg font-semibold text-white mb-4"${_scopeId}>OPcache Memory</h3><div class="flex items-end gap-3 mb-4"${_scopeId}><span class="${ssrRenderClass([opcacheEnabled.value ? "text-blue-400" : "text-gray-500", "text-4xl font-bold"])}"${_scopeId}>${ssrInterpolate(opcacheEnabled.value ? formatBytes(memoryUsage.value.usedMemory) : "N/A")}</span>`,
                );
                if (opcacheEnabled.value) {
                  _push2(
                    `<span class="text-sm text-gray-500 mb-1"${_scopeId}> / ${ssrInterpolate(formatBytes(totalMemory.value))}</span>`,
                  );
                } else {
                  _push2(`<!---->`);
                }
                _push2(`</div>`);
                if (opcacheEnabled.value) {
                  _push2(
                    `<div class="space-y-3"${_scopeId}><div class="w-full bg-gray-800 rounded-full h-2.5"${_scopeId}><div class="bg-blue-500 h-2.5 rounded-full transition-all" style="${ssrRenderStyle({ width: memoryPercent.value + "%" })}"${_scopeId}></div></div><div class="flex justify-between text-sm text-gray-400"${_scopeId}><span${_scopeId}>${ssrInterpolate(memoryPercent.value)}% used</span><span${_scopeId}>${ssrInterpolate(formatBytes(memoryUsage.value.freeMemory))} free</span></div></div>`,
                  );
                } else {
                  _push2(
                    `<p class="text-sm text-gray-500"${_scopeId}>OPcache is not enabled on this instance.</p>`,
                  );
                }
                _push2(
                  `</div><div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50"${_scopeId}><h3 class="text-lg font-semibold text-white mb-4"${_scopeId}>Cached Scripts</h3><div class="flex items-end gap-3"${_scopeId}><span class="text-4xl font-bold text-purple-400"${_scopeId}>${ssrInterpolate(opcacheEnabled.value ? opcacheStats.value.cachedScripts.toLocaleString() : "N/A")}</span>`,
                );
                if (opcacheEnabled.value) {
                  _push2(
                    `<span class="text-sm text-gray-500 mb-1"${_scopeId}> / ${ssrInterpolate(opcacheStats.value.maxCachedKeys.toLocaleString())} slots </span>`,
                  );
                } else {
                  _push2(`<!---->`);
                }
                _push2(`</div>`);
                if (opcacheEnabled.value) {
                  _push2(
                    `<p class="mt-2 text-sm text-gray-400"${_scopeId}> PHP scripts compiled and cached in memory for reuse across requests. </p>`,
                  );
                } else {
                  _push2(`<!---->`);
                }
                _push2(
                  `</div><div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50"${_scopeId}><h3 class="text-lg font-semibold text-white mb-4"${_scopeId}>Runtime Info</h3><dl class="space-y-2 text-sm"${_scopeId}><div class="flex justify-between"${_scopeId}><dt class="text-gray-400"${_scopeId}>PHP Version</dt><dd class="text-white font-mono"${_scopeId}>${ssrInterpolate(__props.phpVersion)}</dd></div><div class="flex justify-between"${_scopeId}><dt class="text-gray-400"${_scopeId}>Runtime</dt><dd class="text-orange-400 font-mono"${_scopeId}>WebAssembly</dd></div><div class="flex justify-between"${_scopeId}><dt class="text-gray-400"${_scopeId}>OPcache</dt><dd class="${ssrRenderClass([opcacheEnabled.value ? "text-green-400" : "text-gray-500", "font-mono"])}"${_scopeId}>${ssrInterpolate(opcacheEnabled.value ? "Enabled" : "Disabled")}</dd></div></dl></div></div></div>`,
                );
              } else {
                return [
                  createVNode("div", { class: "max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16" }, [
                    createVNode("div", { class: "text-center mb-12" }, [
                      createVNode(
                        "h1",
                        { class: "text-3xl sm:text-4xl font-bold text-white" },
                        "Performance",
                      ),
                      createVNode(
                        "p",
                        { class: "mt-3 text-gray-400 max-w-xl mx-auto" },
                        " Real metrics from this running instance. OPcache keeps warm requests fast. ",
                      ),
                    ]),
                    createVNode(
                      "div",
                      { class: "mb-12 p-6 rounded-2xl border border-gray-800 bg-gray-900/50" },
                      [
                        createVNode(
                          "div",
                          { class: "flex flex-col sm:flex-row items-center justify-between gap-4" },
                          [
                            createVNode("div", null, [
                              createVNode(
                                "h2",
                                { class: "text-lg font-semibold text-white" },
                                "Client-Side Latency Test",
                              ),
                              createVNode(
                                "p",
                                { class: "text-sm text-gray-400 mt-1" },
                                "Measure round-trip time from your browser to this worker.",
                              ),
                            ]),
                            createVNode(
                              "button",
                              {
                                class:
                                  "px-5 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-400 text-white font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed",
                                disabled: measuring.value,
                                onClick: measureLatency,
                              },
                              toDisplayString(measuring.value ? "Measuring..." : "Try It"),
                              9,
                              ["disabled"],
                            ),
                          ],
                        ),
                        latency.value !== null
                          ? (openBlock(),
                            createBlock(
                              "div",
                              {
                                key: 0,
                                class: "mt-6 flex flex-wrap items-end gap-6",
                              },
                              [
                                createVNode("div", null, [
                                  createVNode(
                                    "div",
                                    { class: "text-sm text-gray-500 uppercase tracking-wider" },
                                    "Last Request",
                                  ),
                                  createVNode("div", { class: "text-3xl font-bold text-white" }, [
                                    createTextVNode(toDisplayString(latency.value), 1),
                                    createVNode("span", { class: "text-lg text-gray-400" }, "ms"),
                                  ]),
                                ]),
                                measurements.value.length > 1
                                  ? (openBlock(),
                                    createBlock("div", { key: 0 }, [
                                      createVNode(
                                        "div",
                                        { class: "text-sm text-gray-500 uppercase tracking-wider" },
                                        "Average (" +
                                          toDisplayString(measurements.value.length) +
                                          " reqs)",
                                        1,
                                      ),
                                      createVNode(
                                        "div",
                                        { class: "text-3xl font-bold text-white" },
                                        [
                                          createTextVNode(
                                            toDisplayString(
                                              Math.round(
                                                measurements.value.reduce((a, b) => a + b, 0) /
                                                  measurements.value.length,
                                              ),
                                            ),
                                            1,
                                          ),
                                          createVNode(
                                            "span",
                                            { class: "text-lg text-gray-400" },
                                            "ms",
                                          ),
                                        ],
                                      ),
                                    ]))
                                  : createCommentVNode("", true),
                                measurements.value.length >= 2
                                  ? (openBlock(),
                                    createBlock(
                                      "div",
                                      {
                                        key: 1,
                                        class: "text-sm text-gray-500",
                                      },
                                      [
                                        createTextVNode(" First request (cold): "),
                                        createVNode(
                                          "span",
                                          { class: "text-gray-300" },
                                          toDisplayString(measurements.value[0]) + "ms",
                                          1,
                                        ),
                                        createTextVNode(" · Latest (warm): "),
                                        createVNode(
                                          "span",
                                          { class: "text-gray-300" },
                                          toDisplayString(
                                            measurements.value[measurements.value.length - 1],
                                          ) + "ms",
                                          1,
                                        ),
                                      ],
                                    ))
                                  : createCommentVNode("", true),
                              ],
                            ))
                          : createCommentVNode("", true),
                      ],
                    ),
                    createVNode("div", { class: "grid grid-cols-1 md:grid-cols-2 gap-6" }, [
                      createVNode(
                        "div",
                        { class: "p-6 rounded-2xl border border-gray-800 bg-gray-900/50" },
                        [
                          createVNode(
                            "h3",
                            { class: "text-lg font-semibold text-white mb-4" },
                            "OPcache Hit Rate",
                          ),
                          createVNode("div", { class: "flex items-end gap-3 mb-4" }, [
                            createVNode(
                              "span",
                              {
                                class: [
                                  "text-4xl font-bold",
                                  opcacheEnabled.value ? "text-green-400" : "text-gray-500",
                                ],
                              },
                              toDisplayString(
                                opcacheEnabled.value ? `${hitPercent.value}%` : "Off",
                              ),
                              3,
                            ),
                          ]),
                          opcacheEnabled.value
                            ? (openBlock(),
                              createBlock(
                                "div",
                                {
                                  key: 0,
                                  class: "space-y-3",
                                },
                                [
                                  createVNode(
                                    "div",
                                    { class: "w-full bg-gray-800 rounded-full h-2.5" },
                                    [
                                      createVNode(
                                        "div",
                                        {
                                          class: "bg-green-500 h-2.5 rounded-full transition-all",
                                          style: { width: hitPercent.value + "%" },
                                        },
                                        null,
                                        4,
                                      ),
                                    ],
                                  ),
                                  createVNode(
                                    "div",
                                    { class: "flex justify-between text-sm text-gray-400" },
                                    [
                                      createVNode(
                                        "span",
                                        null,
                                        toDisplayString(opcacheStats.value.hits.toLocaleString()) +
                                          " hits",
                                        1,
                                      ),
                                      createVNode(
                                        "span",
                                        null,
                                        toDisplayString(
                                          opcacheStats.value.misses.toLocaleString(),
                                        ) + " misses",
                                        1,
                                      ),
                                    ],
                                  ),
                                ],
                              ))
                            : (openBlock(),
                              createBlock(
                                "p",
                                {
                                  key: 1,
                                  class: "text-sm text-gray-500",
                                },
                                "OPcache is not enabled on this instance.",
                              )),
                        ],
                      ),
                      createVNode(
                        "div",
                        { class: "p-6 rounded-2xl border border-gray-800 bg-gray-900/50" },
                        [
                          createVNode(
                            "h3",
                            { class: "text-lg font-semibold text-white mb-4" },
                            "OPcache Memory",
                          ),
                          createVNode("div", { class: "flex items-end gap-3 mb-4" }, [
                            createVNode(
                              "span",
                              {
                                class: [
                                  "text-4xl font-bold",
                                  opcacheEnabled.value ? "text-blue-400" : "text-gray-500",
                                ],
                              },
                              toDisplayString(
                                opcacheEnabled.value
                                  ? formatBytes(memoryUsage.value.usedMemory)
                                  : "N/A",
                              ),
                              3,
                            ),
                            opcacheEnabled.value
                              ? (openBlock(),
                                createBlock(
                                  "span",
                                  {
                                    key: 0,
                                    class: "text-sm text-gray-500 mb-1",
                                  },
                                  " / " + toDisplayString(formatBytes(totalMemory.value)),
                                  1,
                                ))
                              : createCommentVNode("", true),
                          ]),
                          opcacheEnabled.value
                            ? (openBlock(),
                              createBlock(
                                "div",
                                {
                                  key: 0,
                                  class: "space-y-3",
                                },
                                [
                                  createVNode(
                                    "div",
                                    { class: "w-full bg-gray-800 rounded-full h-2.5" },
                                    [
                                      createVNode(
                                        "div",
                                        {
                                          class: "bg-blue-500 h-2.5 rounded-full transition-all",
                                          style: { width: memoryPercent.value + "%" },
                                        },
                                        null,
                                        4,
                                      ),
                                    ],
                                  ),
                                  createVNode(
                                    "div",
                                    { class: "flex justify-between text-sm text-gray-400" },
                                    [
                                      createVNode(
                                        "span",
                                        null,
                                        toDisplayString(memoryPercent.value) + "% used",
                                        1,
                                      ),
                                      createVNode(
                                        "span",
                                        null,
                                        toDisplayString(formatBytes(memoryUsage.value.freeMemory)) +
                                          " free",
                                        1,
                                      ),
                                    ],
                                  ),
                                ],
                              ))
                            : (openBlock(),
                              createBlock(
                                "p",
                                {
                                  key: 1,
                                  class: "text-sm text-gray-500",
                                },
                                "OPcache is not enabled on this instance.",
                              )),
                        ],
                      ),
                      createVNode(
                        "div",
                        { class: "p-6 rounded-2xl border border-gray-800 bg-gray-900/50" },
                        [
                          createVNode(
                            "h3",
                            { class: "text-lg font-semibold text-white mb-4" },
                            "Cached Scripts",
                          ),
                          createVNode("div", { class: "flex items-end gap-3" }, [
                            createVNode(
                              "span",
                              { class: "text-4xl font-bold text-purple-400" },
                              toDisplayString(
                                opcacheEnabled.value
                                  ? opcacheStats.value.cachedScripts.toLocaleString()
                                  : "N/A",
                              ),
                              1,
                            ),
                            opcacheEnabled.value
                              ? (openBlock(),
                                createBlock(
                                  "span",
                                  {
                                    key: 0,
                                    class: "text-sm text-gray-500 mb-1",
                                  },
                                  " / " +
                                    toDisplayString(
                                      opcacheStats.value.maxCachedKeys.toLocaleString(),
                                    ) +
                                    " slots ",
                                  1,
                                ))
                              : createCommentVNode("", true),
                          ]),
                          opcacheEnabled.value
                            ? (openBlock(),
                              createBlock(
                                "p",
                                {
                                  key: 0,
                                  class: "mt-2 text-sm text-gray-400",
                                },
                                " PHP scripts compiled and cached in memory for reuse across requests. ",
                              ))
                            : createCommentVNode("", true),
                        ],
                      ),
                      createVNode(
                        "div",
                        { class: "p-6 rounded-2xl border border-gray-800 bg-gray-900/50" },
                        [
                          createVNode(
                            "h3",
                            { class: "text-lg font-semibold text-white mb-4" },
                            "Runtime Info",
                          ),
                          createVNode("dl", { class: "space-y-2 text-sm" }, [
                            createVNode("div", { class: "flex justify-between" }, [
                              createVNode("dt", { class: "text-gray-400" }, "PHP Version"),
                              createVNode(
                                "dd",
                                { class: "text-white font-mono" },
                                toDisplayString(__props.phpVersion),
                                1,
                              ),
                            ]),
                            createVNode("div", { class: "flex justify-between" }, [
                              createVNode("dt", { class: "text-gray-400" }, "Runtime"),
                              createVNode(
                                "dd",
                                { class: "text-orange-400 font-mono" },
                                "WebAssembly",
                              ),
                            ]),
                            createVNode("div", { class: "flex justify-between" }, [
                              createVNode("dt", { class: "text-gray-400" }, "OPcache"),
                              createVNode(
                                "dd",
                                {
                                  class: [
                                    opcacheEnabled.value ? "text-green-400" : "text-gray-500",
                                    "font-mono",
                                  ],
                                },
                                toDisplayString(opcacheEnabled.value ? "Enabled" : "Disabled"),
                                3,
                              ),
                            ]),
                          ]),
                        ],
                      ),
                    ]),
                  ]),
                ];
              }
            }),
            _: 1,
          },
          _parent,
        ),
      );
      _push(`<!--]-->`);
    };
  },
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add(
    "resources/js/Pages/Performance.vue",
  );
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const __vite_glob_0_3 = /* @__PURE__ */ Object.freeze(
  /* @__PURE__ */ Object.defineProperty(
    {
      __proto__: null,
      default: _sfc_main,
    },
    Symbol.toStringTag,
    { value: "Module" },
  ),
);
function render(page) {
  return createInertiaApp({
    page,
    render: renderToString,
    resolve: (name) => {
      const pages = /* @__PURE__ */ Object.assign({
        "./Pages/Architecture.vue": __vite_glob_0_0,
        "./Pages/Features.vue": __vite_glob_0_1,
        "./Pages/Home.vue": __vite_glob_0_2,
        "./Pages/Performance.vue": __vite_glob_0_3,
      });
      return pages[`./Pages/${name}.vue`];
    },
    setup({ App, props, plugin }) {
      return createSSRApp({ render: () => h(App, props) }).use(plugin);
    },
  });
}
export { render as default };
