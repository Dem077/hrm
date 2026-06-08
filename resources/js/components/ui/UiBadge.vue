<script setup lang="ts">
defineProps<{
    label: string;
    color?: string;
}>();

const colorClasses: Record<string, string> = {
    gray: 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-700',
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800/60',
    danger: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-800/60',
    warning: 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800/60',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
        :class="colorClasses[color ?? 'gray'] ?? colorClasses.gray"
    >
        {{ label }}
    </span>
</template>
