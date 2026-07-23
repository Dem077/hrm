<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiSelect from '@/components/ui/UiSelect.vue';

const props = defineProps<{
    open: boolean;
    runId: number;
    selectedCount: number;
    employeeIds: number[];
}>();

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const form = useForm({
    employee_ids: [] as number[],
    type: 'addition' as 'addition' | 'deduction',
    title: '',
    amount: '',
    remarks: '',
});

const canSubmit = computed(
    () =>
        props.employeeIds.length > 0 &&
        form.title.trim() !== '' &&
        Number(form.amount) > 0 &&
        !form.processing,
);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.reset();
        form.clearErrors();
        form.type = 'addition';
        form.employee_ids = [...props.employeeIds];
    },
);

watch(
    () => props.employeeIds,
    (ids) => {
        if (props.open) {
            form.employee_ids = [...ids];
        }
    },
);

function close(): void {
    emit('close');
}

function submit(): void {
    form.employee_ids = [...props.employeeIds];
    form.post(`/payroll/${props.runId}/adjustments/bulk`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('success');
            close();
        },
    });
}
</script>

<template>
    <UiModal
        :open="open"
        title="Bulk manual adjustment"
        :description="`Apply the same adjustment to ${selectedCount} selected employee${selectedCount === 1 ? '' : 's'}.`"
        max-width="md"
        @close="close"
    >
        <div class="space-y-4">
            <UiSelect v-model="form.type" label="Type" :error="form.errors.type">
                <option value="addition">Addition</option>
                <option value="deduction">Deduction</option>
            </UiSelect>
            <UiInput v-model="form.title" label="Title" :error="form.errors.title" placeholder="e.g. Festival bonus" />
            <UiInput
                v-model="form.amount"
                type="number"
                label="Amount"
                :error="form.errors.amount"
                placeholder="0.00"
                min="0.01"
                step="0.01"
            />
            <UiInput v-model="form.remarks" label="Remarks (optional)" :error="form.errors.remarks" />
            <p v-if="form.errors.employee_ids" class="text-xs text-red-600 dark:text-red-400">
                {{ form.errors.employee_ids }}
            </p>
            <p v-if="form.errors.run" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.run }}</p>
        </div>
        <template #footer>
            <UiButton variant="ghost" :disabled="form.processing" @click="close">Cancel</UiButton>
            <UiButton variant="primary" :disabled="!canSubmit" @click="submit">
                {{ form.processing ? 'Saving…' : `Apply to ${selectedCount}` }}
            </UiButton>
        </template>
    </UiModal>
</template>
