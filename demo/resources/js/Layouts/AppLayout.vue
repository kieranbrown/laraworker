<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const mobileMenuOpen = ref(false);

const navLinks = [
    { name: 'Home', route: '/' },
    { name: 'Performance', route: '/performance' },
    { name: 'Architecture', route: '/architecture' },
    { name: 'Features', route: '/features' },
];
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-950 text-gray-100">
        <!-- Navigation -->
        <nav class="border-b border-gray-800 bg-gray-950/80 backdrop-blur-sm sticky top-0 z-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <Link href="/" class="flex items-center gap-2 font-bold text-lg text-white">
                        <span class="text-orange-400">&#9651;</span>
                        Laraworker
                    </Link>

                    <!-- Desktop nav -->
                    <div class="hidden md:flex items-center gap-1">
                        <Link
                            v-for="link in navLinks"
                            :key="link.route"
                            :href="link.route"
                            class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/50 transition-colors"
                        >
                            {{ link.name }}
                        </Link>
                        <a
                            href="https://github.com/kieranbrown/laraworker"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ml-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/50 transition-colors"
                        >
                            GitHub
                        </a>
                    </div>

                    <!-- Mobile hamburger -->
                    <button
                        class="md:hidden p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800/50"
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
                <div v-if="mobileMenuOpen" class="md:hidden pb-4 border-t border-gray-800 mt-2 pt-2">
                    <Link
                        v-for="link in navLinks"
                        :key="link.route"
                        :href="link.route"
                        class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/50 transition-colors"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.name }}
                    </Link>
                    <a
                        href="https://github.com/kieranbrown/laraworker"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/50 transition-colors"
                    >
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
        <footer class="border-t border-gray-800 bg-gray-950">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
                    <div class="flex items-center gap-2">
                        <span class="text-orange-400">&#9651;</span>
                        <span>Powered by <strong class="text-gray-400">Laraworker</strong></span>
                    </div>
                    <div>
                        Laravel on Cloudflare Workers via PHP WebAssembly
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
