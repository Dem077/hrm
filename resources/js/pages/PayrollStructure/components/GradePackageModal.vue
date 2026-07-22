<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { componentToPayrollItem, usesGlobalRateCalculation } from '@/lib/payroll';
import DesignationTotalsSummary from '@/pages/PayrollStructure/components/DesignationTotalsSummary.vue';
import PayrollItemsEditor from '@/pages/PayrollStructure/components/PayrollItemsEditor.vue';
import type { DesignationPayrollItem, LoanBankOption, PayrollComponent, StructureGradePackage } from '@/types/payroll';

const props = defineProps<{
    open: boolean;
    grade: StructureGradePackage | null;
    components: PayrollComponent[];
    loanBanks: LoanBankOption[];
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const form = useForm({
    items: [] as DesignationPayrollItem[],
});

watch(
    () => [props.open, props.grade, props.components] as const,
    ([open, grade]) => {
        if (!open || !grade) {
            return;
        }

        form.clearErrors();
        form.items = ensureMandatoryItems(
            grade.items
                .filter((item) => !usesGlobalRateCalculation(item.calculation_method))
                .map((item) => ({ ...item })),
            props.components,
        );
    },
    { immediate: true },
);

function ensureMandatoryItems(
    items: DesignationPayrollItem[],
    components: PayrollComponent[],
): DesignationPayrollItem[] {
    const existingIds = new Set(items.map((item) => item.payroll_component_id));
    const missing = components
        .filter(
            (component) =>
                component.is_mandatory &&
                component.is_active &&
                component.id &&
                !usesGlobalRateCalculation(component.calculation_method) &&
                !existingIds.has(component.id),
        )
        .map((component) => componentToPayrollItem(component, 0));

    return missing.length ? [...missing, ...items] : items;
}

function submit() {
    if (!props.grade?.id) {
        return;
    }

    form.put(`/payroll-structure/grades/${props.grade.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <UiModal
        :open="open"
        :title="grade ? `Payroll package · ${grade.label}` : 'Payroll package'"
        :description="grade?.path_label"
        max-width="xl"
        @close="emit('close')"
    >
        <form v-if="grade" class="space-y-5" @submit.prevent="submit">
            <DesignationTotalsSummary :items="form.items" />

            <PayrollItemsEditor
                :items="form.items"
                :components="components"
                :loan-banks="loanBanks"
                :errors="form.errors"
                @update:items="form.items = $event"
            />

            <div class="flex justify-end gap-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" variant="primary" :disabled="form.processing">Save package</UiButton>
            </div>
        </form>
    </UiModal>
</template>
