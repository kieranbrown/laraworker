<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

interface Props {
    phpVersion: string;
    extensions: string[];
    sapi: string;
    opcacheEnabled: boolean;
    inertiaVersion: string;
}

defineProps<Props>();

const layers = [
    {
        label: 'Browser',
        color: 'blue',
        description: 'Your visitor\'s browser makes a request. Inertia handles client-side navigation after the first load.',
        runs: 'Client',
    },
    {
        label: 'Cloudflare Edge',
        color: 'orange',
        description: 'Static assets (CSS, JS, images) are served directly from Cloudflare\'s edge network — fast and cheap.',
        runs: 'Edge (300+ locations)',
    },
    {
        label: 'Cloudflare Worker',
        color: 'amber',
        description: 'Dynamic requests hit a Worker that initializes the PHP WASM runtime and executes your Laravel app.',
        runs: 'Worker (V8 isolate)',
    },
    {
        label: 'PHP WASM Runtime',
        color: 'red',
        description: 'Full PHP 8.5 compiled to WebAssembly. OPcache persists compiled opcodes across requests within the same isolate.',
        runs: 'Inside Worker',
    },
    {
        label: 'Laravel Framework',
        color: 'rose',
        description: 'Your complete Laravel application — routing, controllers, Blade, Eloquent, middleware — all running in WASM.',
        runs: 'Inside PHP WASM',
    },
];

const colorClasses: Record<string, { border: string; bg: string; text: string; dot: string }> = {
    blue: { border: 'border-blue-500/30', bg: 'bg-blue-500/10', text: 'text-blue-400', dot: 'bg-blue-400' },
    orange: { border: 'border-orange-500/30', bg: 'bg-orange-500/10', text: 'text-orange-400', dot: 'bg-orange-400' },
    amber: { border: 'border-amber-500/30', bg: 'bg-amber-500/10', text: 'text-amber-400', dot: 'bg-amber-400' },
    red: { border: 'border-red-500/30', bg: 'bg-red-500/10', text: 'text-red-400', dot: 'bg-red-400' },
    rose: { border: 'border-rose-500/30', bg: 'bg-rose-500/10', text: 'text-rose-400', dot: 'bg-rose-400' },
};
</script>

<template>
    <Head title="Architecture — Laraworker" />
    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center mb-12">
                <h1 class="text-3xl sm:text-4xl font-bold text-white">Architecture</h1>
                <p class="mt-3 text-gray-400 max-w-xl mx-auto">
                    How a Laravel request flows from browser to response — entirely on Cloudflare's network.
                </p>
            </div>

            <!-- Request flow -->
            <div class="relative max-w-2xl mx-auto mb-16">
                <div
                    v-for="(layer, index) in layers"
                    :key="layer.label"
                    class="relative pl-10 pb-8 last:pb-0"
                >
                    <!-- Vertical line -->
                    <div
                        v-if="index < layers.length - 1"
                        class="absolute left-4 top-6 bottom-0 w-px bg-gray-800"
                    />

                    <!-- Dot -->
                    <div
                        class="absolute left-2.5 top-1.5 w-3 h-3 rounded-full ring-4 ring-gray-950"
                        :class="colorClasses[layer.color].dot"
                    />

                    <!-- Card -->
                    <div
                        class="p-5 rounded-xl border"
                        :class="[colorClasses[layer.color].border, colorClasses[layer.color].bg]"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-2">
                            <h3 class="text-lg font-semibold text-white">{{ layer.label }}</h3>
                            <span
                                class="text-xs font-mono px-2 py-0.5 rounded-full border border-gray-700 text-gray-400 w-fit"
                            >
                                {{ layer.runs }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-400 leading-relaxed">{{ layer.description }}</p>
                    </div>
                </div>
            </div>

            <!-- Technical details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Runtime details -->
                <div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50">
                    <h3 class="text-lg font-semibold text-white mb-4">Runtime Details</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-400">PHP Version</dt>
                            <dd class="text-white font-mono">{{ phpVersion }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">SAPI</dt>
                            <dd class="text-white font-mono">{{ sapi }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">OPcache</dt>
                            <dd :class="opcacheEnabled ? 'text-green-400' : 'text-gray-500'" class="font-mono">
                                {{ opcacheEnabled ? 'Enabled' : 'Disabled' }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Inertia</dt>
                            <dd class="text-white font-mono">{{ inertiaVersion }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Loaded extensions -->
                <div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50">
                    <h3 class="text-lg font-semibold text-white mb-4">
                        PHP Extensions
                        <span class="text-sm font-normal text-gray-500">({{ extensions.length }})</span>
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="ext in extensions"
                            :key="ext"
                            class="px-2.5 py-1 rounded-lg bg-gray-800 text-xs font-mono text-gray-300"
                        >
                            {{ ext }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
