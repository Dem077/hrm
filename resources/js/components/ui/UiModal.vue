<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        maxWidth?: 'sm' | 'md' | 'lg' | 'xl';
    }>(),
    {
        maxWidth: 'lg',
    },
);

defineEmits<{
    close: [];
}>();

const maxWidthClass = computed(() => {
    const sizes = {
        sm: 'max-w-md',
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-4xl',
    };

    return sizes[props.maxWidth];
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[300] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="title.replace(/\s+/g, '-').toLowerCase()"
        >
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="$emit('close')" />

            <div
                class="relative flex max-h-[90vh] w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated"
                :class="maxWidthClass"
            >
                <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <h2 :id="title.replace(/\s+/g, '-').toLowerCase()" class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {{ title }}
                    </h2>
                    <p v-if="description" class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ description }}
                    </p>
                </div>

                <div class="overflow-y-auto px-6 py-5">
                    <slot />
                </div>

                <div
                    v-if="$slots.footer"
                    class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4 dark:border-slate-800"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
