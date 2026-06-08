<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue';

defineProps<{
    open: boolean;
    message: string;
    processing?: boolean;
}>();

defineEmits<{
    confirm: [];
    cancel: [];
}>();
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[300] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="leave-punch-conflict-title"
        >
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="$emit('cancel')" />

            <div
                class="relative w-full max-w-md rounded-2xl border border-amber-200 bg-white p-6 shadow-xl dark:border-amber-900/60 dark:bg-surface-elevated"
            >
                <h2 id="leave-punch-conflict-title" class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                    Punch records found
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    {{ message }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <UiButton type="button" variant="ghost" :disabled="processing" @click="$emit('cancel')">
                        Cancel
                    </UiButton>
                    <UiButton type="button" variant="primary" :disabled="processing" @click="$emit('confirm')">
                        Continue anyway
                    </UiButton>
                </div>
            </div>
        </div>
    </Teleport>
</template>
