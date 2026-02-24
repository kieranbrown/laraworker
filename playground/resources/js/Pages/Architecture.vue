<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';

interface Props {
    phpVersion: string;
    extensions: string[];
    sapi: string;
    opcacheEnabled: boolean;
    inertiaVersion: string;
}

defineProps<Props>();

const expandedLayer = ref<number | null>(null);

function toggleLayer(index: number): void {
    expandedLayer.value = expandedLayer.value === index ? null : index;
}

const layers = [
    {
        label: 'Browser',
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>`,
        gradient: 'from-blue-500 to-blue-600',
        ring: 'ring-blue-500/20',
        dot: 'bg-blue-400',
        description: 'Your visitor\'s browser makes a request. Inertia handles client-side navigation after the first load.',
        runs: 'Client',
        detail: 'After the initial full page load, Inertia.js intercepts link clicks and makes XHR requests instead, swapping page components without a full reload. This gives SPA-like speed with server-side routing.',
    },
    {
        label: 'Cloudflare Edge',
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 003 12c0-1.605.42-3.113 1.157-4.418"/></svg>`,
        gradient: 'from-cyan-500 to-blue-500',
        ring: 'ring-cyan-500/20',
        dot: 'bg-cyan-400',
        description: 'Static assets (CSS, JS, images) are served directly from Cloudflare\'s edge network — fast and cheap.',
        runs: 'Edge (300+ locations)',
        detail: 'Vite-built assets are uploaded to Cloudflare\'s KV/R2 and served from the nearest edge location. This means CSS, JS, fonts, and images never touch your Worker, keeping costs down and latency minimal.',
    },
    {
        label: 'Cloudflare Worker',
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z"/></svg>`,
        gradient: 'from-indigo-500 to-purple-500',
        ring: 'ring-indigo-500/20',
        dot: 'bg-indigo-400',
        description: 'Dynamic requests hit a Worker that initializes the PHP WASM runtime and executes your Laravel app.',
        runs: 'Worker (V8 isolate)',
        detail: 'The Worker boots the php-wasm binary, mounts your compiled PHP application, and processes the HTTP request. The V8 isolate starts in microseconds — no container spin-up or VM boot.',
    },
    {
        label: 'PHP WASM Runtime',
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z"/></svg>`,
        gradient: 'from-purple-500 to-violet-500',
        ring: 'ring-purple-500/20',
        dot: 'bg-purple-400',
        description: 'Full PHP 8.5 compiled to WebAssembly. OPcache persists compiled opcodes across requests within the same isolate.',
        runs: 'Inside Worker',
        detail: 'PHP is compiled to WASM using Emscripten. The OPcache extension persists compiled opcodes in the V8 isolate\'s memory, so subsequent requests skip the compilation step entirely — typically 2-3x faster.',
    },
    {
        label: 'Laravel Framework',
        icon: `<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
        gradient: 'from-rose-500 to-pink-500',
        ring: 'ring-rose-500/20',
        dot: 'bg-rose-400',
        description: 'Your complete Laravel application — routing, controllers, Blade, Eloquent, middleware — all running in WASM.',
        runs: 'Inside PHP WASM',
        detail: 'The full Laravel framework boots inside the WASM runtime. Controllers handle requests, Blade renders templates, and Inertia serves Vue page data — all processed at the edge with sub-second response times.',
    },
];
</script>

<template>
    <Head title="Architecture — Laraworker" />
    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center mb-14">
                <h1 class="text-3xl sm:text-4xl font-bold text-white">Architecture</h1>
                <p class="mt-3 text-gray-400 max-w-xl mx-auto">
                    How a Laravel request flows from browser to response — entirely on Cloudflare's network.
                </p>
            </div>

            <!-- Horizontal flow (desktop) / Vertical flow (mobile) -->
            <div class="hidden lg:block mb-16">
                <div class="flex items-start gap-0">
                    <template v-for="(layer, index) in layers" :key="layer.label">
                        <!-- Node -->
                        <div class="flex-1 relative group">
                            <button
                                class="w-full text-left p-5 rounded-xl ring-1 bg-white/[0.02] transition-all duration-300 hover:bg-white/[0.04] cursor-pointer"
                                :class="[
                                    expandedLayer === index ? 'ring-white/20 bg-white/[0.04]' : 'ring-white/10',
                                    layer.ring
                                ]"
                                @click="toggleLayer(index)"
                            >
                                <div class="flex items-center gap-2 mb-2">
                                    <span
                                        class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br text-white shrink-0"
                                        :class="layer.gradient"
                                        v-html="layer.icon"
                                    />
                                    <h3 class="text-sm font-semibold text-white leading-tight">{{ layer.label }}</h3>
                                </div>
                                <span class="text-[10px] font-mono text-gray-500 uppercase tracking-wider">{{ layer.runs }}</span>
                                <p class="mt-2 text-xs text-gray-400 leading-relaxed line-clamp-3">{{ layer.description }}</p>
                            </button>

                            <!-- Expanded detail -->
                            <div
                                v-if="expandedLayer === index"
                                class="mt-3 p-4 rounded-xl ring-1 ring-white/10 bg-slate-900 text-sm text-gray-400 leading-relaxed animate-fade-in"
                            >
                                {{ layer.detail }}
                            </div>
                        </div>

                        <!-- Arrow between nodes -->
                        <div v-if="index < layers.length - 1" class="flex items-center px-1 pt-9 shrink-0">
                            <svg class="w-6 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2 8h18m0 0l-5-5m5 5l-5 5" />
                            </svg>
                        </div>
                    </template>
                </div>
                <p class="text-center text-xs text-gray-600 mt-4">Click any node to expand details</p>
            </div>

            <!-- Mobile: vertical timeline -->
            <div class="lg:hidden mb-16">
                <div class="relative max-w-2xl mx-auto">
                    <div
                        v-for="(layer, index) in layers"
                        :key="layer.label"
                        class="relative pl-12 pb-8 last:pb-0"
                    >
                        <!-- Vertical line -->
                        <div
                            v-if="index < layers.length - 1"
                            class="absolute left-[18px] top-10 bottom-0 w-px bg-gradient-to-b from-gray-700 to-gray-800"
                        />

                        <!-- Dot -->
                        <div
                            class="absolute left-2.5 top-2 w-4 h-4 rounded-full ring-4 ring-slate-950 flex items-center justify-center"
                            :class="layer.dot"
                        >
                            <div class="w-1.5 h-1.5 rounded-full bg-white/60" />
                        </div>

                        <!-- Card -->
                        <button
                            class="w-full text-left p-5 rounded-xl ring-1 bg-white/[0.02] transition-all duration-300 cursor-pointer"
                            :class="expandedLayer === index ? 'ring-white/20 bg-white/[0.04]' : 'ring-white/10'"
                            @click="toggleLayer(index)"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-br text-white shrink-0"
                                        :class="layer.gradient"
                                        v-html="layer.icon"
                                    />
                                    <h3 class="text-lg font-semibold text-white">{{ layer.label }}</h3>
                                </div>
                                <span class="text-xs font-mono px-2 py-0.5 rounded-full ring-1 ring-white/10 text-gray-400 w-fit">
                                    {{ layer.runs }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-400 leading-relaxed">{{ layer.description }}</p>
                        </button>

                        <div
                            v-if="expandedLayer === index"
                            class="mt-3 p-4 rounded-xl ring-1 ring-white/10 bg-slate-900 text-sm text-gray-400 leading-relaxed animate-fade-in"
                        >
                            {{ layer.detail }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Technical details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Runtime details -->
                <div class="p-6 rounded-2xl ring-1 ring-white/10 bg-white/[0.02]">
                    <div class="flex items-center gap-2 mb-5">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                        <h3 class="text-lg font-semibold text-white">Runtime Details</h3>
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-400">PHP Version</dt>
                            <dd class="text-white font-mono px-2 py-0.5 rounded bg-white/5">{{ phpVersion }}</dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-400">SAPI</dt>
                            <dd class="text-white font-mono px-2 py-0.5 rounded bg-white/5">{{ sapi }}</dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-400">OPcache</dt>
                            <dd :class="opcacheEnabled ? 'text-green-400' : 'text-gray-500'" class="font-mono">
                                {{ opcacheEnabled ? 'Enabled' : 'Disabled' }}
                            </dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-400">Inertia</dt>
                            <dd class="text-white font-mono px-2 py-0.5 rounded bg-white/5">{{ inertiaVersion }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Loaded extensions -->
                <div class="p-6 rounded-2xl ring-1 ring-white/10 bg-white/[0.02]">
                    <div class="flex items-center gap-2 mb-5">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.627 48.627 0 01-4.121-.244l-1.671-.306A1.875 1.875 0 013 6.468V5.25a1.875 1.875 0 011.875-1.875h14.25A1.875 1.875 0 0121 5.25v1.218a1.875 1.875 0 01-1.152 1.731l-1.671.306c-1.37.252-2.75.417-4.121.244a.64.64 0 01-.657-.643v0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 9v9.75A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V9"/></svg>
                        <h3 class="text-lg font-semibold text-white">
                            PHP Extensions
                            <span class="text-sm font-normal text-gray-500">({{ extensions.length }})</span>
                        </h3>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="ext in extensions"
                            :key="ext"
                            class="px-2.5 py-1 rounded-lg bg-white/5 ring-1 ring-white/5 text-xs font-mono text-gray-300"
                        >
                            {{ ext }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
