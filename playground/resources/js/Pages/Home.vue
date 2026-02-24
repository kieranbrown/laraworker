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
        icon: '&#9889;',
    },
    {
        title: 'OPcache',
        description: 'Persistent opcode cache across requests — warm responses are up to 3x faster.',
        icon: '&#128640;',
    },
    {
        title: 'Edge Assets',
        description: 'Static files served from Cloudflare edge, never touching Workers.',
        icon: '&#127760;',
    },
    {
        title: 'Full Laravel',
        description: 'Eloquent, Blade, queues, routing — the complete Laravel framework.',
        icon: '&#9881;',
    },
];
</script>

<template>
    <Head title="Laraworker — Laravel on Cloudflare Workers" />
    <AppLayout>
        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-orange-500/5 to-transparent pointer-events-none" />
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32 relative">
                <div class="text-center max-w-3xl mx-auto">
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white">
                        Laravel on
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-amber-300">
                            Cloudflare Workers
                        </span>
                    </h1>
                    <p class="mt-6 text-lg sm:text-xl text-gray-400 leading-relaxed">
                        Run your full Laravel application at the edge — powered by PHP compiled to WebAssembly.
                        No containers, no cold starts from VMs, just your code running globally.
                    </p>
                    <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a
                            href="https://github.com/nicekiwi/laraworker"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="px-6 py-3 rounded-xl bg-orange-500 hover:bg-orange-400 text-white font-semibold transition-colors"
                        >
                            View on GitHub
                        </a>
                        <a
                            href="/performance"
                            class="px-6 py-3 rounded-xl border border-gray-700 hover:border-gray-500 text-gray-300 hover:text-white font-semibold transition-colors"
                        >
                            See Performance
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats bar -->
        <section class="border-y border-gray-800 bg-gray-900/50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 text-center">
                    <div>
                        <div class="text-sm text-gray-500 uppercase tracking-wider">PHP Version</div>
                        <div class="mt-1 text-xl font-bold text-white">{{ phpVersion }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 uppercase tracking-wider">Laravel</div>
                        <div class="mt-1 text-xl font-bold text-white">v{{ laravelVersion }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 uppercase tracking-wider">Runtime</div>
                        <div class="mt-1 text-xl font-bold text-orange-400">WebAssembly</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 uppercase tracking-wider">OPcache</div>
                        <div class="mt-1 text-xl font-bold" :class="opcacheEnabled ? 'text-green-400' : 'text-gray-500'">
                            {{ opcacheEnabled ? 'Enabled' : 'Disabled' }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature cards -->
        <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="text-2xl sm:text-3xl font-bold text-white text-center">How It Works</h2>
            <p class="mt-3 text-gray-400 text-center max-w-xl mx-auto">
                Laraworker compiles PHP to WebAssembly and runs your Laravel app inside Cloudflare Workers.
            </p>
            <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div
                    v-for="card in featureCards"
                    :key="card.title"
                    class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50 hover:border-gray-700 transition-colors"
                >
                    <div class="text-3xl mb-4" v-html="card.icon" />
                    <h3 class="text-lg font-semibold text-white">{{ card.title }}</h3>
                    <p class="mt-2 text-sm text-gray-400 leading-relaxed">{{ card.description }}</p>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
            <div class="rounded-2xl border border-gray-800 bg-gradient-to-r from-orange-500/10 to-amber-500/10 p-8 sm:p-12 text-center">
                <h2 class="text-2xl sm:text-3xl font-bold text-white">Ready to deploy Laravel at the edge?</h2>
                <p class="mt-3 text-gray-400 max-w-lg mx-auto">
                    Get started with Laraworker and run your Laravel application on Cloudflare's global network.
                </p>
                <a
                    href="https://github.com/nicekiwi/laraworker"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-6 inline-block px-6 py-3 rounded-xl bg-orange-500 hover:bg-orange-400 text-white font-semibold transition-colors"
                >
                    Get Started on GitHub
                </a>
            </div>
        </section>
    </AppLayout>
</template>
