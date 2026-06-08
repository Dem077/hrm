<script setup lang="ts">
defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    accent?: 'amber' | 'emerald' | 'sky' | 'slate';
}>();

const accents = {
    amber: 'from-amber-500/15 to-amber-500/5 text-amber-700 dark:from-amber-500/20 dark:to-amber-500/5 dark:text-amber-400',
    emerald: 'from-emerald-500/15 to-emerald-500/5 text-emerald-700 dark:from-emerald-500/20 dark:to-emerald-500/5 dark:text-emerald-400',
    sky: 'from-sky-500/15 to-sky-500/5 text-sky-700 dark:from-sky-500/20 dark:to-sky-500/5 dark:text-sky-400',
    slate: 'from-slate-500/15 to-slate-500/5 text-slate-600 dark:from-slate-500/20 dark:to-slate-500/5 dark:text-slate-400',
};
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-surface p-5 shadow-sm shadow-slate-200/50 dark:border-slate-800 dark:shadow-black/20">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ label }}</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ value }}</p>
                <p v-if="hint" class="mt-2 text-xs text-slate-500">{{ hint }}</p>
            </div>
            <div class="rounded-xl bg-gradient-to-br p-3" :class="accents[accent ?? 'slate']">
                <slot name="icon" />
            </div>
        </div>
    </div>
</template>
