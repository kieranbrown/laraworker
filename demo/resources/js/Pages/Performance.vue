<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';

interface OpcacheStats {
    hits: number;
    misses: number;
    hitRate: number;
    cachedScripts: number;
    cachedKeys: number;
    maxCachedKeys: number;
}

interface MemoryUsage {
    usedMemory: number;
    freeMemory: number;
    wastedMemory: number;
    wastedPercentage: number;
}

interface Props {
    phpVersion: string;
    opcacheEnabled: boolean;
    opcacheStats: OpcacheStats;
    memoryUsage: MemoryUsage;
}

const props = defineProps<Props>();

const latency = ref<number | null>(null);
const measuring = ref(false);
const measurements = ref<number[]>([]);

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
}

async function measureLatency(): Promise<void> {
    measuring.value = true;
    const start = performance.now();
    try {
        await fetch('/performance', { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': '' } });
    } catch {
        // Ignore fetch errors for timing purposes
    }
    const elapsed = Math.round(performance.now() - start);
    latency.value = elapsed;
    measurements.value.push(elapsed);
    measuring.value = false;
}

const totalMemory = (props.memoryUsage.usedMemory + props.memoryUsage.freeMemory) || 1;
const memoryPercent = Math.round((props.memoryUsage.usedMemory / totalMemory) * 100);
const totalRequests = (props.opcacheStats.hits + props.opcacheStats.misses) || 1;
const hitPercent = Math.round((props.opcacheStats.hits / totalRequests) * 100);
</script>

<template>
    <Head title="Performance — Laraworker" />
    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center mb-12">
                <h1 class="text-3xl sm:text-4xl font-bold text-white">Performance</h1>
                <p class="mt-3 text-gray-400 max-w-xl mx-auto">
                    Real metrics from this running instance. OPcache keeps warm requests fast.
                </p>
            </div>

            <!-- Live latency test -->
            <div class="mb-12 p-6 rounded-2xl border border-gray-800 bg-gray-900/50">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Client-Side Latency Test</h2>
                        <p class="text-sm text-gray-400 mt-1">Measure round-trip time from your browser to this worker.</p>
                    </div>
                    <button
                        class="px-5 py-2.5 rounded-xl bg-orange-500 hover:bg-orange-400 text-white font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="measuring"
                        @click="measureLatency"
                    >
                        {{ measuring ? 'Measuring...' : 'Try It' }}
                    </button>
                </div>
                <div v-if="latency !== null" class="mt-6 flex flex-wrap items-end gap-6">
                    <div>
                        <div class="text-sm text-gray-500 uppercase tracking-wider">Last Request</div>
                        <div class="text-3xl font-bold text-white">{{ latency }}<span class="text-lg text-gray-400">ms</span></div>
                    </div>
                    <div v-if="measurements.length > 1">
                        <div class="text-sm text-gray-500 uppercase tracking-wider">Average ({{ measurements.length }} reqs)</div>
                        <div class="text-3xl font-bold text-white">
                            {{ Math.round(measurements.reduce((a, b) => a + b, 0) / measurements.length) }}<span class="text-lg text-gray-400">ms</span>
                        </div>
                    </div>
                    <div v-if="measurements.length >= 2" class="text-sm text-gray-500">
                        First request (cold): <span class="text-gray-300">{{ measurements[0] }}ms</span>
                        &middot;
                        Latest (warm): <span class="text-gray-300">{{ measurements[measurements.length - 1] }}ms</span>
                    </div>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- OPcache hits -->
                <div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50">
                    <h3 class="text-lg font-semibold text-white mb-4">OPcache Hit Rate</h3>
                    <div class="flex items-end gap-3 mb-4">
                        <span class="text-4xl font-bold" :class="opcacheEnabled ? 'text-green-400' : 'text-gray-500'">
                            {{ opcacheEnabled ? `${hitPercent}%` : 'Off' }}
                        </span>
                    </div>
                    <div v-if="opcacheEnabled" class="space-y-3">
                        <div class="w-full bg-gray-800 rounded-full h-2.5">
                            <div class="bg-green-500 h-2.5 rounded-full transition-all" :style="{ width: hitPercent + '%' }" />
                        </div>
                        <div class="flex justify-between text-sm text-gray-400">
                            <span>{{ opcacheStats.hits.toLocaleString() }} hits</span>
                            <span>{{ opcacheStats.misses.toLocaleString() }} misses</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">OPcache is not enabled on this instance.</p>
                </div>

                <!-- Memory usage -->
                <div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50">
                    <h3 class="text-lg font-semibold text-white mb-4">OPcache Memory</h3>
                    <div class="flex items-end gap-3 mb-4">
                        <span class="text-4xl font-bold" :class="opcacheEnabled ? 'text-blue-400' : 'text-gray-500'">
                            {{ opcacheEnabled ? formatBytes(memoryUsage.usedMemory) : 'N/A' }}
                        </span>
                        <span v-if="opcacheEnabled" class="text-sm text-gray-500 mb-1">
                            / {{ formatBytes(totalMemory) }}
                        </span>
                    </div>
                    <div v-if="opcacheEnabled" class="space-y-3">
                        <div class="w-full bg-gray-800 rounded-full h-2.5">
                            <div class="bg-blue-500 h-2.5 rounded-full transition-all" :style="{ width: memoryPercent + '%' }" />
                        </div>
                        <div class="flex justify-between text-sm text-gray-400">
                            <span>{{ memoryPercent }}% used</span>
                            <span>{{ formatBytes(memoryUsage.freeMemory) }} free</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">OPcache is not enabled on this instance.</p>
                </div>

                <!-- Cached scripts -->
                <div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50">
                    <h3 class="text-lg font-semibold text-white mb-4">Cached Scripts</h3>
                    <div class="flex items-end gap-3">
                        <span class="text-4xl font-bold text-purple-400">
                            {{ opcacheEnabled ? opcacheStats.cachedScripts.toLocaleString() : 'N/A' }}
                        </span>
                        <span v-if="opcacheEnabled" class="text-sm text-gray-500 mb-1">
                            / {{ opcacheStats.maxCachedKeys.toLocaleString() }} slots
                        </span>
                    </div>
                    <p v-if="opcacheEnabled" class="mt-2 text-sm text-gray-400">
                        PHP scripts compiled and cached in memory for reuse across requests.
                    </p>
                </div>

                <!-- PHP info -->
                <div class="p-6 rounded-2xl border border-gray-800 bg-gray-900/50">
                    <h3 class="text-lg font-semibold text-white mb-4">Runtime Info</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-400">PHP Version</dt>
                            <dd class="text-white font-mono">{{ phpVersion }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Runtime</dt>
                            <dd class="text-orange-400 font-mono">WebAssembly</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">OPcache</dt>
                            <dd :class="opcacheEnabled ? 'text-green-400' : 'text-gray-500'" class="font-mono">
                                {{ opcacheEnabled ? 'Enabled' : 'Disabled' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
