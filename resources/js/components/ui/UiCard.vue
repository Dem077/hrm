<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
    padding?: 'sm' | 'md' | 'none';
}>();

const paddingClasses = {
    sm: 'p-4',
    md: 'p-6',
    none: 'p-0',
};
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-surface shadow-sm shadow-slate-200/50 dark:border-slate-800 dark:shadow-black/20">
        <div v-if="title || description" class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
            <h2 v-if="title" class="text-base font-semibold text-slate-900 dark:text-white">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
        </div>
        <div :class="paddingClasses[padding ?? 'md']">
            <slot />
        </div>
    </section>
</template>
