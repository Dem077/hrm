<script setup lang="ts">
import { computed, reactive, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiSelect from '@/components/ui/UiSelect.vue';

const props = defineProps<{
    open: boolean;
    selectedCount: number;
    employeeIds: number[];
    submitting?: boolean;
}>();

const emit = defineEmits<{
    close: [];
    submit: [
        payload: {
            employee_ids: number[];
            type: 'addition' | 'deduction';
            title: string;
            amount: number;
            remarks: string | null;
        },
    ];
}>();

const form = reactive({
    type: 'addition' as 'addition' | 'deduction',
    title: '',
    amount: '',
    remarks: '',
    errors: {} as Record<string, string>,
});

const canSubmit = computed(
    () =>
        props.employeeIds.length > 0 &&
        form.title.trim() !== '' &&
        Number(form.amount) > 0 &&
        !props.submitting,
);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.type = 'addition';
        form.title = '';
        form.amount = '';
        form.remarks = '';
        form.errors = {};
    },
);

function close(): void {
    emit('close');
}

function submit(): void {
    form.errors = {};

    if (!canSubmit.value) {
        return;
    }

    emit('submit', {
        employee_ids: [...props.employeeIds],
        type: form.type,
        title: form.title.trim(),
        amount: Number(form.amount),
        remarks: form.remarks.trim() || null,
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
            <UiButton variant="ghost" :disabled="submitting" @click="close">Cancel</UiButton>
            <UiButton variant="primary" :disabled="!canSubmit" @click="submit">
                {{ submitting ? 'Queuing…' : `Apply to ${selectedCount}` }}
            </UiButton>
        </template>
    </UiModal>
</template>
