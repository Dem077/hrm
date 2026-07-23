<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        label: string;
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
        size?: 'sm' | 'md';
        align?: 'left' | 'right';
        disabled?: boolean;
    }>(),
    {
        variant: 'ghost',
        size: 'sm',
        align: 'right',
        disabled: false,
    },
);

const open = ref(false);
const root = ref<HTMLElement | null>(null);

function toggle(): void {
    open.value = !open.value;
}

function close(): void {
    open.value = false;
}

function onDocumentPointerDown(event: PointerEvent): void {
    const target = event.target as Node | null;
    if (!target || root.value?.contains(target)) {
        return;
    }

    close();
}

function onDocumentKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onDocumentKeydown);
});

defineExpose({ close });
</script>

<template>
    <div ref="root" class="relative inline-flex">
        <UiButton type="button" :variant="variant" :size="size" :disabled="disabled" @click="toggle">
            <span class="inline-flex items-center gap-1">
                <slot name="trigger">{{ label }}</slot>
                <svg class="h-3.5 w-3.5 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                        clip-rule="evenodd"
                    />
                </svg>
            </span>
        </UiButton>

        <div
            v-if="open"
            :class="
                cn(
                    'absolute z-40 mt-1 min-w-[10rem] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-surface-elevated',
                    align === 'right' ? 'right-0' : 'left-0',
                )
            "
            role="menu"
        >
            <div class="flex flex-col" @click="close">
                <slot />
            </div>
        </div>
    </div>
</template>
