<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue';
import UiModal from '@/components/ui/UiModal.vue';
import type { JobProgressPayload } from './useJobProgress';

withDefaults(
    defineProps<{
        open: boolean;
        progress: JobProgressPayload | null;
        percent: number;
        message: string;
        error: string | null;
        canCancel: boolean;
        cancelling: boolean;
        isTerminal: boolean;
        title?: string;
        description?: string;
        unitLabel?: string;
    }>(),
    {
        title: 'Working…',
        description: 'You can cancel before this finishes.',
        unitLabel: 'items',
    },
);

defineEmits<{
    close: [];
    cancel: [];
}>();
</script>

<template>
    <UiModal
        :open="open"
        :title="title"
        :description="description"
        max-width="sm"
        @close="isTerminal ? $emit('close') : undefined"
    >
        <div class="space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                {{ message }}
            </p>

            <div>
                <div class="mb-1 flex items-center justify-between text-xs font-medium text-slate-500 dark:text-slate-400">
                    <span>
                        <template v-if="progress && progress.total > 0">
                            {{ progress.done }} of {{ progress.total }} {{ unitLabel }}
                        </template>
                        <template v-else>
                            Preparing…
                        </template>
                    </span>
                    <span>{{ percent }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div
                        class="h-full rounded-full bg-sky-600 transition-all duration-300 dark:bg-sky-500"
                        :style="{ width: `${Math.max(0, Math.min(100, percent))}%` }"
                    />
                </div>
            </div>

            <p
                v-if="error"
                class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300"
            >
                {{ error }}
            </p>

            <p
                v-else-if="progress?.status === 'cancelled'"
                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200"
            >
                Job cancelled.
            </p>

            <p
                v-else-if="progress?.status === 'completed'"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                Done.
            </p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <UiButton
                    v-if="canCancel"
                    variant="danger"
                    :disabled="cancelling"
                    @click="$emit('cancel')"
                >
                    {{ cancelling ? 'Cancelling…' : 'Cancel' }}
                </UiButton>
                <UiButton
                    v-if="isTerminal"
                    variant="primary"
                    @click="$emit('close')"
                >
                    Close
                </UiButton>
            </div>
        </template>
    </UiModal>
</template>
