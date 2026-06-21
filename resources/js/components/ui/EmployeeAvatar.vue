<script setup lang="ts">
withDefaults(
    defineProps<{
        photoUrl?: string | null;
        name: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        photoUrl: null,
        size: 'md',
    },
);

const sizeClasses = {
    sm: 'h-10 w-10 text-sm ring-2',
    md: 'h-20 w-20 text-xl ring-4',
    lg: 'h-32 w-32 text-4xl ring-4',
};
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-brand-500/15 via-brand-600/10 to-slate-100 font-semibold text-brand-700 shadow-md shadow-slate-200/70 ring-white dark:from-brand-500/20 dark:via-brand-950/30 dark:to-surface-elevated dark:text-brand-300 dark:shadow-black/30 dark:ring-surface"
        :class="sizeClasses[size]"
    >
        <img v-if="photoUrl" :src="photoUrl" :alt="`${name} profile photo`" class="h-full w-full object-cover" />
        <span v-else>{{ name.charAt(0).toUpperCase() }}</span>
    </div>
</template>
