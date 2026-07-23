<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        label: string;
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

defineEmits<{
    click: [event: MouseEvent];
}>();
</script>

<template>
    <button
        type="button"
        :title="label"
        :aria-label="label"
        :disabled="disabled"
        :class="
            cn(
                'inline-flex h-7 w-7 items-center justify-center rounded-lg border border-transparent text-slate-500 transition',
                'hover:bg-surface-elevated hover:text-slate-900',
                'disabled:cursor-not-allowed disabled:opacity-40',
                'dark:text-slate-400 dark:hover:text-slate-100',
            )
        "
        @click="$emit('click', $event)"
    >
        <slot />
    </button>
</template>
