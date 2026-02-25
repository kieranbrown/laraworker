<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const mobileMenuOpen = ref(false);
const page = usePage();

const currentPath = computed(() => page.url);

const navLinks = [
    { name: 'Home', route: '/' },
    { name: 'Performance', route: '/performance' },
    { name: 'Architecture', route: '/architecture' },
    { name: 'Features', route: '/features' },
];

function isActive(route: string): boolean {
    if (route === '/') return currentPath.value === '/';
    return currentPath.value.startsWith(route);
}
</script>

<template>
    <div class="min-h-screen flex flex-col bg-slate-950 text-gray-100">
        <!-- Navigation -->
        <nav class="border-b border-white/5 bg-slate-950/70 backdrop-blur-md sticky top-0 z-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <a href="/" class="flex items-center gap-2.5 font-bold text-lg text-white group">
                        <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white text-sm transition-shadow group-hover:shadow-lg group-hover:shadow-indigo-500/25">
                            &#9651;
                        </span>
                        <span>Laraworker</span>
                    </a>

                    <!-- Desktop nav -->
                    <div class="hidden md:flex items-center gap-1">
                        <a
                            v-for="link in navLinks"
                            :key="link.route"
                            :href="link.route"
                            class="relative px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                            :class="isActive(link.route)
                                ? 'text-white nav-active'
                                : 'text-gray-400 hover:text-white hover:bg-white/5'"
                        >
                            {{ link.name }}
                        </a>
                        <a
                            href="https://github.com/nicekiwi/laraworker"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ml-3 flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white ring-1 ring-white/10 hover:ring-white/20 transition-all"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                            GitHub
                        </a>
                    </div>

                    <!-- Mobile hamburger -->
                    <button
                        class="md:hidden p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/5"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <svg v-if="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Mobile menu -->
                <div v-if="mobileMenuOpen" class="md:hidden pb-4 border-t border-white/5 mt-2 pt-3 space-y-1">
                    <a
                        v-for="link in navLinks"
                        :key="link.route"
                        :href="link.route"
                        class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                        :class="isActive(link.route)
                            ? 'text-white bg-white/5'
                            : 'text-gray-400 hover:text-white hover:bg-white/5'"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.name }}
                    </a>
                    <a
                        href="https://github.com/nicekiwi/laraworker"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        GitHub
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-white/5 bg-slate-950">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
                    <div class="flex items-center gap-2.5">
                        <span class="flex items-center justify-center w-6 h-6 rounded bg-gradient-to-br from-indigo-500 to-purple-600 text-white text-[10px]">&#9651;</span>
                        <span>Powered by <strong class="text-gray-300">Laraworker</strong></span>
                    </div>
                    <div class="text-gray-600">
                        Laravel on Cloudflare Workers via PHP WebAssembly
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
