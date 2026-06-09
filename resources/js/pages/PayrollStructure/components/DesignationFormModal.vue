<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import DesignationTotalsSummary from '@/pages/PayrollStructure/components/DesignationTotalsSummary.vue';
import PayrollItemsEditor from '@/pages/PayrollStructure/components/PayrollItemsEditor.vue';
import type { Designation, DesignationPayrollItem, PayrollComponent } from '@/types/payroll';

const props = defineProps<{
    open: boolean;
    designation: Designation | null;
    emptyDesignation: Designation;
    defaultItems: DesignationPayrollItem[];
    components: PayrollComponent[];
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const form = useForm({
    ...props.emptyDesignation,
    items: [] as DesignationPayrollItem[],
});

watch(
    () => [props.open, props.designation] as const,
    ([open, designation]) => {
        if (!open) {
            return;
        }

        form.clearErrors();

        if (designation) {
            form.name = designation.name;
            form.code = designation.code ?? '';
            form.description = designation.description ?? '';
            form.sort_order = designation.sort_order;
            form.is_active = designation.is_active;
            form.items = designation.items.map((item) => ({ ...item }));
            return;
        }

        form.reset();
        form.defaults({
            ...props.emptyDesignation,
            items: props.defaultItems.map((item) => ({ ...item })),
        });
        form.items = props.defaultItems.map((item) => ({ ...item }));
    },
    { immediate: true },
);

function submit() {
    if (props.designation?.id) {
        form.put(`/payroll-structure/designations/${props.designation.id}`, {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });

        return;
    }

    form.post('/payroll-structure/designations', {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <UiModal
        :open="open"
        :title="designation ? 'Edit designation' : 'Add designation'"
        description="Set fixed amounts and daily rates for this role."
        max-width="xl"
        @close="emit('close')"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2">
                <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
                <UiInput v-model="form.code" label="Code" :error="form.errors.code" />
                <UiInput v-model.number="form.sort_order" label="Sort order" type="number" min="0" :error="form.errors.sort_order" />
                <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                    Active
                </label>
                <div class="md:col-span-2">
                    <UiInput v-model="form.description" label="Description" :error="form.errors.description" />
                </div>
            </div>

            <PayrollItemsEditor
                :items="form.items"
                :components="components"
                :errors="form.errors"
                @update:items="form.items = $event"
            />

            <DesignationTotalsSummary :items="form.items" />

            <div class="flex justify-end gap-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" :disabled="form.processing">
                    {{ designation ? 'Update designation' : 'Create designation' }}
                </UiButton>
            </div>
        </form>
    </UiModal>
</template>
