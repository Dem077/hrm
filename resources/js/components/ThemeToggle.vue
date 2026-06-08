<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';

const { isDark, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="isDark"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        class="relative inline-flex h-9 w-[4.25rem] shrink-0 items-center rounded-full border border-slate-200 bg-slate-100 p-1 transition dark:border-slate-700 dark:bg-surface-elevated"
        @click="toggleTheme"
    >
        <span
            class="absolute inset-y-1 left-1 w-7 rounded-full bg-white shadow-sm transition-transform duration-200 dark:bg-slate-600"
            :class="isDark ? 'translate-x-[2rem]' : 'translate-x-0'"
        />
        <span class="relative z-10 flex w-full items-center justify-between px-1.5">
            <svg
                class="h-3.5 w-3.5 transition"
                :class="isDark ? 'text-slate-500' : 'text-amber-500'"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                />
            </svg>
            <svg
                class="h-3.5 w-3.5 transition"
                :class="isDark ? 'text-slate-200' : 'text-slate-400'"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                />
            </svg>
        </span>
    </button>
</template>
