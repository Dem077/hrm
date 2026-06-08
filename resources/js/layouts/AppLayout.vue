<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import FlashMessage from '@/components/FlashMessage.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { useSidebar } from '@/composables/useSidebar';
import type { Auth } from '@/types/auth';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage<{ auth: Auth }>();
const { collapsed, mobileOpen, toggleCollapsed, openMobile, closeMobile } = useSidebar();

const user = computed(() => page.props.auth.user);

const navItems = [
    {
        label: 'Dashboard',
        href: '/',
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        match: (url: string) => url === '/' || url.startsWith('/dashboard'),
    },
    {
        label: 'Employees',
        href: '/employees',
        icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        match: (url: string) => url.startsWith('/employees'),
    },
    {
        label: 'Departments',
        href: '/departments',
        icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        match: (url: string) => url.startsWith('/departments'),
    },
    {
        label: 'ZKT Devices',
        href: '/zkt-devices',
        icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z',
        match: (url: string) => url.startsWith('/zkt-devices'),
    },
    {
        label: 'Punch Logs',
        href: '/zkt-attendance-logs',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        match: (url: string) => url.startsWith('/zkt-attendance-logs'),
    },
    {
        label: 'Attendance Sheet',
        href: '/attendance-sheet',
        icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        match: (url: string) => url.startsWith('/attendance-sheet'),
    },
    {
        label: 'Global Settings',
        href: '/attendance-settings',
        icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        match: (url: string) => url.startsWith('/attendance-settings'),
    },
];

const sidebarWidthClass = computed(() => (collapsed.value ? 'lg:w-[4.5rem]' : 'lg:w-[280px]'));
const mainOffsetClass = computed(() => (collapsed.value ? 'lg:pl-[4.5rem]' : 'lg:pl-[280px]'));

function isActive(match: (url: string) => boolean): boolean {
    return match(page.url);
}

function userInitials(name?: string): string {
    if (!name) {
        return 'U';
    }

    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

watch(mobileOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});
</script>

<template>
    <div class="min-h-screen bg-canvas">
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm dark:bg-black/60 lg:hidden"
            @click="closeMobile"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[min(100vw-3rem,280px)] flex-col overflow-x-hidden border-r border-sidebar-border bg-sidebar transition-[width,transform] duration-300 ease-out lg:translate-x-0"
            :class="[
                mobileOpen ? 'translate-x-0' : '-translate-x-full',
                'lg:translate-x-0',
                sidebarWidthClass,
            ]"
        >
            <!-- Header -->
            <div
                class="flex shrink-0 items-center border-b border-sidebar-border px-3"
                :class="collapsed ? 'h-16 lg:h-auto lg:flex-col lg:gap-2 lg:py-3 lg:px-2' : 'h-16'"
            >
                <Link
                    href="/"
                    class="flex items-center gap-3 overflow-hidden"
                    :class="collapsed ? 'min-w-0 flex-1 lg:flex-none lg:justify-center' : 'min-w-0 flex-1'"
                    @click="closeMobile"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold text-white shadow-lg shadow-brand-600/20">
                        H
                    </div>
                    <div :class="collapsed ? 'lg:hidden' : ''" class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">HRM</p>
                        <p class="truncate text-xs text-sidebar-muted">Attendance</p>
                    </div>
                </Link>

                <button
                    type="button"
                    class="hidden shrink-0 rounded-lg p-2 text-sidebar-muted transition hover:bg-sidebar-active hover:text-slate-900 dark:hover:text-white lg:inline-flex"
                    :class="collapsed ? 'lg:w-full lg:justify-center' : ''"
                    :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    @click="toggleCollapsed"
                >
                    <svg
                        class="h-5 w-5 transition-transform duration-300"
                        :class="collapsed ? 'rotate-180' : ''"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
            </div>

            <!-- Nav -->
            <nav
                class="flex-1 space-y-1 overflow-y-auto overflow-x-hidden py-4"
                :class="collapsed ? 'px-3 lg:px-2' : 'px-3'"
            >
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    :title="collapsed ? item.label : undefined"
                    class="flex items-center rounded-xl text-sm font-medium transition"
                    :class="[
                        collapsed ? 'gap-3 px-3 py-2.5 lg:justify-center lg:gap-0 lg:px-0 lg:py-3' : 'gap-3 px-3 py-2.5',
                        isActive(item.match)
                            ? 'bg-brand-600/15 text-brand-600 ring-1 ring-brand-500/20 dark:text-brand-400'
                            : 'text-sidebar-muted hover:bg-sidebar-active hover:text-slate-900 dark:hover:text-slate-100',
                    ]"
                    @click="closeMobile"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span :class="collapsed ? 'truncate lg:hidden' : 'truncate'">{{ item.label }}</span>
                </Link>
            </nav>

            <!-- Footer -->
            <div
                class="shrink-0 border-t border-sidebar-border p-3"
                :class="collapsed ? 'lg:p-2' : ''"
            >
                <div
                    class="flex items-center gap-3 rounded-xl bg-surface-muted px-3 py-3 dark:bg-surface-elevated/80"
                    :class="collapsed ? 'lg:justify-center lg:px-0 lg:py-2' : ''"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600/15 text-xs font-semibold text-brand-700 dark:bg-brand-600/20 dark:text-brand-400">
                        {{ userInitials(user?.name) }}
                    </div>
                    <div :class="collapsed ? 'lg:hidden' : ''" class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ user?.name }}</p>
                        <p class="truncate text-xs text-sidebar-muted">{{ user?.email }}</p>
                    </div>
                </div>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    :title="collapsed ? 'Sign out' : undefined"
                    class="mt-3 flex w-full items-center justify-center rounded-xl border border-sidebar-border text-sidebar-muted transition hover:border-slate-300 hover:bg-sidebar-active hover:text-slate-900 dark:hover:border-slate-600 dark:hover:text-white"
                    :class="collapsed ? 'p-2.5 lg:px-0' : 'px-3 py-2 text-sm'"
                    @click="closeMobile"
                >
                    <svg
                        class="h-5 w-5 shrink-0"
                        :class="collapsed ? 'lg:block' : 'hidden'"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">Sign out</span>
                </Link>
            </div>
        </aside>

        <div class="flex min-h-screen flex-col transition-[padding] duration-300 ease-out" :class="mainOffsetClass">
            <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-canvas/80 backdrop-blur-xl dark:border-slate-800/80">
                <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex rounded-xl border border-slate-200 bg-surface-elevated p-2.5 text-slate-600 transition hover:bg-surface-muted dark:border-slate-800 dark:text-slate-300 lg:hidden"
                            @click="openMobile"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <div v-if="title" class="min-w-0 lg:hidden">
                            <h1 class="truncate text-base font-semibold text-slate-900 dark:text-white">{{ title }}</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 sm:gap-4">
                        <ThemeToggle />

                        <div class="hidden items-center gap-3 lg:flex">
                            <div class="text-right">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ user?.name }}</p>
                                <p class="text-xs text-slate-500">Administrator</p>
                            </div>
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600/15 text-xs font-semibold text-brand-700 dark:bg-brand-600/20 dark:text-brand-400">
                                {{ userInitials(user?.name) }}
                            </div>
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-500 transition hover:bg-surface-elevated hover:text-slate-900 dark:border-slate-800 dark:text-slate-400 dark:hover:text-white lg:hidden"
                        >
                            Sign out
                        </Link>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8 lg:py-8">
                <div v-if="title" class="mb-8 hidden lg:block">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ title }}</h1>
                    <p v-if="description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
                </div>

                <FlashMessage />
                <slot />
            </main>
        </div>
    </div>
</template>
