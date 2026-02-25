<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

interface Feature {
    name: string;
    description: string;
    status: 'supported' | 'coming-soon';
}

interface Props {
    features: Feature[];
}

defineProps<Props>();
</script>

<template>
    <Head title="Features — Laraworker" />
    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center mb-12">
                <h1 class="text-3xl sm:text-4xl font-bold text-white">Features</h1>
                <p class="mt-3 text-gray-400 max-w-xl mx-auto">
                    What's supported when running Laravel on Cloudflare Workers with Laraworker.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="feature in features"
                    :key="feature.name"
                    class="p-6 rounded-2xl border bg-gray-900/50 transition-colors"
                    :class="feature.status === 'supported' ? 'border-gray-800 hover:border-gray-700' : 'border-gray-800/50 opacity-75'"
                >
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <h3 class="text-lg font-semibold text-white">{{ feature.name }}</h3>
                        <span
                            class="shrink-0 px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="feature.status === 'supported'
                                ? 'bg-green-500/10 text-green-400 border border-green-500/20'
                                : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20'"
                        >
                            {{ feature.status === 'supported' ? 'Supported' : 'Coming Soon' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ feature.description }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
