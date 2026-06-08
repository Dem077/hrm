<script setup lang="ts">
defineProps<{
    status: string;
    label: string;
    color?: string;
}>();

const colorClasses: Record<string, string> = {
    gray: 'bg-stone-100 text-stone-700',
    success: 'bg-emerald-100 text-emerald-800',
    danger: 'bg-red-100 text-red-800',
    warning: 'bg-amber-100 text-amber-800',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
        :class="colorClasses[color ?? 'gray'] ?? colorClasses.gray"
    >
        {{ label }}
    </span>
</template>
