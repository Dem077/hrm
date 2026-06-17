<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { formatDate } from '@/lib/format';

const props = defineProps<{
    open: boolean;
    employeeName: string;
    date: string;
    punchLabel: string;
    logId: number | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const form = useForm({
    punch_log_ids: [] as number[],
    reason: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen || !props.logId) {
            return;
        }

        form.reset();
        form.punch_log_ids = [props.logId];
        form.reason = '';
        form.clearErrors();
    },
);

function submit() {
    form.delete('/attendance-sheet/manual-punches', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <UiModal
        :open="open"
        title="Remove punch"
        :description="`Remove ${punchLabel} for ${employeeName} on ${formatDate(date)}.`"
        @close="emit('close')"
    >
        <form class="grid gap-4" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Reason for removal</label>
                <textarea
                    v-model="form.reason"
                    rows="3"
                    required
                    placeholder="Explain why this punch is being removed..."
                    class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                />
                <p v-if="form.errors.reason" class="mt-1 text-sm text-red-600">{{ form.errors.reason }}</p>
                <p v-if="form.errors.punch_log_ids" class="mt-1 text-sm text-red-600">{{ form.errors.punch_log_ids }}</p>
            </div>

            <div class="flex justify-end gap-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" variant="danger" :disabled="form.processing">Remove punch</UiButton>
            </div>
        </form>
    </UiModal>
</template>
