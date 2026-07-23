<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        danger?: boolean;
        disabled?: boolean;
    }>(),
    {
        danger: false,
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
        role="menuitem"
        :disabled="disabled"
        :class="
            cn(
                'w-full px-3 py-1.5 text-left text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-40',
                danger
                    ? 'text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40'
                    : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/80',
            )
        "
        @click="$emit('click', $event)"
    >
        <slot />
    </button>
</template>
