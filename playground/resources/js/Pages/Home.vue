<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

interface Props {
    phpVersion: string;
    serverInfo: string;
    laravelVersion: string;
    opcacheEnabled: boolean;
    opcacheStats: {
        hits: number;
        misses: number;
        opcache_hit_rate: number;
    } | null;
}

defineProps<Props>();

const featureCards = [
    {
        title: 'Inertia SSR',
        description: 'Server-side rendered Vue pages built in the worker for instant loads and SEO.',
        icon: `<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>`,
        color: 'from-indigo-500 to-blue-500',
        glow: 'group-hover:shadow-indigo-500/20',
    },
    {
        title: 'OPcache',
        description: 'Persistent opcode cache across requests — warm responses are up to 3x faster.',
        icon: `<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.841m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>`,
        color: 'from-purple-500 to-violet-500',
        glow: 'group-hover:shadow-purple-500/20',
    },
    {
        title: 'Edge Assets',
        description: 'Static files served from Cloudflare edge, never touching Workers.',
        icon: `<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 003 12c0-1.605.42-3.113 1.157-4.418"/></svg>`,
        color: 'from-blue-500 to-cyan-500',
        glow: 'group-hover:shadow-blue-500/20',
    },
    {
        title: 'Full Laravel',
        description: 'Eloquent, Blade, queues, routing — the complete Laravel framework.',
        icon: `<svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
        color: 'from-violet-500 to-purple-500',
        glow: 'group-hover:shadow-violet-500/20',
    },
];
</script>

<template>
    <Head title="Laraworker — Laravel on Cloudflare Workers" />
    <AppLayout>
        <!-- Hero -->
        <section class="relative overflow-hidden">
            <!-- Background effects -->
            <div class="absolute inset-0 bg-dot-pattern opacity-50" />
            <div class="absolute inset-0 bg-gradient-to-b from-indigo-500/5 via-transparent to-transparent" />
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[500px] bg-gradient-to-br from-indigo-500/10 via-purple-500/5 to-transparent rounded-full blur-3xl" />

            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-28 sm:py-36 relative">
                <div class="text-center max-w-3xl mx-auto animate-slide-up">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full ring-1 ring-white/10 bg-white/5 text-sm text-gray-400 mb-8">
                        <span class="w-2 h-2 rounded-full bg-green-400 animate-glow-pulse" />
                        Live on Cloudflare Workers
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-7xl font-bold tracking-tight text-white leading-tight">
                        Laravel on
                        <span class="text-gradient-animated block sm:inline">
                            Cloudflare Workers
                        </span>
                    </h1>
                    <p class="mt-6 text-lg sm:text-xl text-gray-400 leading-relaxed max-w-2xl mx-auto">
                        Run your full Laravel application at the edge — powered by PHP compiled to WebAssembly.
                        No containers, no cold starts from VMs, just your code running globally.
                    </p>
                    <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a
                            href="https://github.com/nicekiwi/laraworker"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="px-6 py-3 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-400 hover:to-purple-500 text-white font-semibold transition-all shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40"
                        >
                            View on GitHub
                        </a>
                        <a
                            href="/performance"
                            class="px-6 py-3 rounded-xl ring-1 ring-white/10 hover:ring-white/20 text-gray-300 hover:text-white font-semibold transition-all hover:bg-white/5"
                        >
                            See Performance &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats bar -->
        <section class="border-y border-white/5 bg-slate-900/50 backdrop-blur-sm">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
                    <div class="stat-glow rounded-xl p-4 bg-white/[0.02]">
                        <div class="text-xs text-gray-500 uppercase tracking-wider font-medium">PHP Version</div>
                        <div class="mt-1.5 text-2xl font-bold text-white font-mono">{{ phpVersion }}</div>
                    </div>
                    <div class="stat-glow rounded-xl p-4 bg-white/[0.02]">
                        <div class="text-xs text-gray-500 uppercase tracking-wider font-medium">Laravel</div>
                        <div class="mt-1.5 text-2xl font-bold text-white font-mono">v{{ laravelVersion }}</div>
                    </div>
                    <div class="stat-glow rounded-xl p-4 bg-white/[0.02]">
                        <div class="text-xs text-gray-500 uppercase tracking-wider font-medium">Runtime</div>
                        <div class="mt-1.5 text-2xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400 font-mono">WASM</div>
                    </div>
                    <div class="stat-glow rounded-xl p-4 bg-white/[0.02]">
                        <div class="text-xs text-gray-500 uppercase tracking-wider font-medium">OPcache</div>
                        <div class="mt-1.5 text-2xl font-bold font-mono" :class="opcacheEnabled ? 'text-green-400' : 'text-gray-500'">
                            {{ opcacheEnabled ? 'Active' : 'Off' }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature cards -->
        <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center mb-14">
                <h2 class="text-2xl sm:text-3xl font-bold text-white">How It Works</h2>
                <p class="mt-3 text-gray-400 max-w-xl mx-auto">
                    Laraworker compiles PHP to WebAssembly and runs your Laravel app inside Cloudflare Workers.
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div
                    v-for="card in featureCards"
                    :key="card.title"
                    class="group relative p-6 rounded-2xl ring-1 ring-white/10 bg-white/[0.02] hover:bg-white/[0.04] transition-all duration-300"
                    :class="card.glow"
                    style="transition-property: all, box-shadow"
                >
                    <div
                        class="w-10 h-10 rounded-xl bg-gradient-to-br flex items-center justify-center text-white mb-5"
                        :class="card.color"
                        v-html="card.icon"
                    />
                    <h3 class="text-lg font-semibold text-white">{{ card.title }}</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">{{ card.description }}</p>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-24">
            <div class="relative rounded-2xl ring-1 ring-white/10 overflow-hidden p-8 sm:p-12 text-center">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/10 via-purple-500/5 to-transparent" />
                <div class="relative">
                    <h2 class="text-2xl sm:text-3xl font-bold text-white">Ready to deploy Laravel at the edge?</h2>
                    <p class="mt-3 text-gray-400 max-w-lg mx-auto">
                        Get started with Laraworker and run your Laravel application on Cloudflare's global network.
                    </p>
                    <a
                        href="https://github.com/nicekiwi/laraworker"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-6 inline-block px-6 py-3 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-400 hover:to-purple-500 text-white font-semibold transition-all shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40"
                    >
                        Get Started on GitHub
                    </a>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
