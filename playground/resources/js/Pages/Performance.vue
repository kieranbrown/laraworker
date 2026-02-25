<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
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
const animatedHitPercent = ref(0);
const animatedMemPercent = ref(0);

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

function latencyColor(ms: number): string {
    if (ms < 200) return 'text-green-400';
    if (ms < 500) return 'text-yellow-400';
    return 'text-orange-400';
}

function latencyBarColor(ms: number): string {
    if (ms < 200) return 'bg-green-500';
    if (ms < 500) return 'bg-yellow-500';
    return 'bg-orange-500';
}

const totalMemory = (props.memoryUsage.usedMemory + props.memoryUsage.freeMemory) || 1;
const memoryPercent = Math.round((props.memoryUsage.usedMemory / totalMemory) * 100);
const totalRequests = (props.opcacheStats.hits + props.opcacheStats.misses) || 1;
const hitPercent = Math.round((props.opcacheStats.hits / totalRequests) * 100);

function animateValue(target: { value: number }, end: number, duration: number): void {
    const start = 0;
    const step = (end - start) / (duration / 16);
    let current = start;
    const interval = setInterval(() => {
        current += step;
        if (current >= end) {
            target.value = end;
            clearInterval(interval);
        } else {
            target.value = Math.round(current);
        }
    }, 16);
}

onMounted(() => {
    if (props.opcacheEnabled) {
        animateValue(animatedHitPercent, hitPercent, 800);
        animateValue(animatedMemPercent, memoryPercent, 800);
    }
});
</script>

<template>
    <Head title="Performance — Laraworker" />
    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <!-- Header -->
            <div class="text-center mb-14">
                <h1 class="text-3xl sm:text-4xl font-bold text-white">Performance</h1>
                <p class="mt-3 text-gray-400 max-w-xl mx-auto">
                    Real metrics from this running instance. OPcache keeps warm requests fast.
                </p>
            </div>

            <!-- Live latency test -->
            <div class="mb-10 p-6 sm:p-8 rounded-2xl ring-1 ring-white/10 bg-white/[0.02]">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                            Client-Side Latency Test
                        </h2>
                        <p class="text-sm text-gray-400 mt-1">Measure round-trip time from your browser to this worker.</p>
                    </div>
                    <button
                        class="px-5 py-2.5 rounded-xl font-semibold transition-all text-white disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="measuring
                            ? 'bg-indigo-500/50 animate-pulse'
                            : 'bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-400 hover:to-purple-500 shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40'"
                        :disabled="measuring"
                        @click="measureLatency"
                    >
                        <span v-if="measuring" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Measuring...
                        </span>
                        <span v-else>Measure</span>
                    </button>
                </div>

                <!-- Results -->
                <div v-if="latency !== null" class="animate-fade-in">
                    <div class="flex flex-wrap items-end gap-8 mb-6">
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider font-medium">Last Request</div>
                            <div class="text-4xl font-bold font-mono" :class="latencyColor(latency)">
                                {{ latency }}<span class="text-lg text-gray-500">ms</span>
                            </div>
                        </div>
                        <div v-if="measurements.length > 1">
                            <div class="text-xs text-gray-500 uppercase tracking-wider font-medium">Average ({{ measurements.length }} reqs)</div>
                            <div class="text-4xl font-bold text-white font-mono">
                                {{ Math.round(measurements.reduce((a, b) => a + b, 0) / measurements.length) }}<span class="text-lg text-gray-500">ms</span>
                            </div>
                        </div>
                    </div>

                    <!-- Measurement bars -->
                    <div v-if="measurements.length > 0" class="space-y-2">
                        <div class="text-xs text-gray-500 uppercase tracking-wider font-medium mb-3">Request History</div>
                        <div v-for="(ms, i) in measurements" :key="i" class="flex items-center gap-3">
                            <span class="text-xs text-gray-500 font-mono w-6 text-right">#{{ i + 1 }}</span>
                            <div class="flex-1 bg-white/5 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="latencyBarColor(ms)"
                                    :style="{ width: Math.min(ms / (Math.max(...measurements) * 1.2) * 100, 100) + '%' }"
                                />
                            </div>
                            <span class="text-xs font-mono w-14 text-right" :class="latencyColor(ms)">{{ ms }}ms</span>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="text-center py-6 text-gray-600">
                    <p class="text-sm">Click "Measure" to test latency. Run multiple times to compare cold vs warm requests.</p>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- OPcache hits -->
                <div class="p-6 rounded-2xl ring-1 ring-white/10 bg-white/[0.02]">
                    <div class="flex items-center gap-2 mb-5">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h3 class="text-lg font-semibold text-white">OPcache Hit Rate</h3>
                    </div>
                    <div class="flex items-end gap-3 mb-5">
                        <span class="text-5xl font-bold font-mono" :class="opcacheEnabled ? 'text-green-400' : 'text-gray-500'">
                            {{ opcacheEnabled ? `${animatedHitPercent}%` : 'Off' }}
                        </span>
                    </div>
                    <div v-if="opcacheEnabled" class="space-y-3">
                        <div class="w-full bg-white/5 rounded-full h-3 overflow-hidden">
                            <div
                                class="bg-gradient-to-r from-green-500 to-emerald-400 h-full rounded-full transition-all duration-1000"
                                :style="{ width: animatedHitPercent + '%' }"
                            />
                        </div>
                        <div class="flex justify-between text-sm text-gray-400">
                            <span class="font-mono">{{ opcacheStats.hits.toLocaleString() }} hits</span>
                            <span class="font-mono">{{ opcacheStats.misses.toLocaleString() }} misses</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">OPcache is not enabled on this instance.</p>
                </div>

                <!-- Memory usage -->
                <div class="p-6 rounded-2xl ring-1 ring-white/10 bg-white/[0.02]">
                    <div class="flex items-center gap-2 mb-5">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>
                        <h3 class="text-lg font-semibold text-white">OPcache Memory</h3>
                    </div>
                    <div class="flex items-end gap-3 mb-5">
                        <span class="text-5xl font-bold font-mono" :class="opcacheEnabled ? 'text-blue-400' : 'text-gray-500'">
                            {{ opcacheEnabled ? formatBytes(memoryUsage.usedMemory) : 'N/A' }}
                        </span>
                        <span v-if="opcacheEnabled" class="text-sm text-gray-500 mb-1.5 font-mono">
                            / {{ formatBytes(totalMemory) }}
                        </span>
                    </div>
                    <div v-if="opcacheEnabled" class="space-y-3">
                        <div class="w-full bg-white/5 rounded-full h-3 overflow-hidden">
                            <div
                                class="bg-gradient-to-r from-blue-500 to-indigo-400 h-full rounded-full transition-all duration-1000"
                                :style="{ width: animatedMemPercent + '%' }"
                            />
                        </div>
                        <div class="flex justify-between text-sm text-gray-400">
                            <span class="font-mono">{{ animatedMemPercent }}% used</span>
                            <span class="font-mono">{{ formatBytes(memoryUsage.freeMemory) }} free</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">OPcache is not enabled on this instance.</p>
                </div>

                <!-- Cached scripts -->
                <div class="p-6 rounded-2xl ring-1 ring-white/10 bg-white/[0.02]">
                    <div class="flex items-center gap-2 mb-5">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/></svg>
                        <h3 class="text-lg font-semibold text-white">Cached Scripts</h3>
                    </div>
                    <div class="flex items-end gap-3">
                        <span class="text-5xl font-bold text-purple-400 font-mono">
                            {{ opcacheEnabled ? opcacheStats.cachedScripts.toLocaleString() : 'N/A' }}
                        </span>
                        <span v-if="opcacheEnabled" class="text-sm text-gray-500 mb-1.5 font-mono">
                            / {{ opcacheStats.maxCachedKeys.toLocaleString() }} slots
                        </span>
                    </div>
                    <p v-if="opcacheEnabled" class="mt-3 text-sm text-gray-400">
                        PHP scripts compiled and cached in memory for reuse across requests.
                    </p>
                </div>

                <!-- PHP info -->
                <div class="p-6 rounded-2xl ring-1 ring-white/10 bg-white/[0.02]">
                    <div class="flex items-center gap-2 mb-5">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                        <h3 class="text-lg font-semibold text-white">Runtime Info</h3>
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-400">PHP Version</dt>
                            <dd class="text-white font-mono px-2 py-0.5 rounded bg-white/5">{{ phpVersion }}</dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-400">Runtime</dt>
                            <dd class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400 font-mono font-semibold">WebAssembly</dd>
                        </div>
                        <div class="flex justify-between items-center">
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
