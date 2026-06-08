<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import FlashMessage from '@/components/FlashMessage.vue';
import type { Auth } from '@/types/auth';

defineProps<{
    title?: string;
}>();

const page = usePage<{ auth: Auth }>();

const user = computed(() => page.props.auth.user);

const navItems = [
    { label: 'Dashboard', href: '/dashboard', match: (url: string) => url === '/dashboard' || url === '/' },
    { label: 'ZKT Devices', href: '/zkt-devices', match: (url: string) => url.startsWith('/zkt-devices') },
    { label: 'Punch Logs', href: '/zkt-attendance-logs', match: (url: string) => url.startsWith('/zkt-attendance-logs') },
];

function isActive(match: (url: string) => boolean): boolean {
    return match(page.url);
}
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900">
        <header class="border-b border-stone-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <Link href="/dashboard" class="text-lg font-semibold tracking-tight text-amber-700">
                        HRM
                    </Link>
                    <nav class="hidden items-center gap-1 md:flex">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            class="rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="
                                isActive(item.match)
                                    ? 'bg-amber-50 text-amber-800'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            "
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>

                <div class="flex items-center gap-4">
                    <span class="hidden text-sm text-stone-500 sm:inline">{{ user?.name }}</span>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded-md border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100"
                    >
                        Log out
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div v-if="title" class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            </div>

            <FlashMessage />

            <slot />
        </main>
    </div>
</template>
