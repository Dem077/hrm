<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="rounded-2xl border border-dashed border-slate-300 bg-surface px-6 py-12 text-center dark:border-slate-700">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-surface-elevated dark:text-slate-400">
            <slot name="icon" />
        </div>
        <h3 class="mt-4 text-base font-medium text-slate-900 dark:text-white">{{ title }}</h3>
        <p v-if="description" class="mx-auto mt-2 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
