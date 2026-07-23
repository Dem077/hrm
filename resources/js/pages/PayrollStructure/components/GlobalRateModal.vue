<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { isPercentageOfBasicCalculation } from '@/lib/payroll';
import PayrollFormulaBuilder from '@/pages/PayrollStructure/components/PayrollFormulaBuilder.vue';
import type { PayrollCalculationMethodOption, PayrollComponent } from '@/types/payroll';

const props = defineProps<{
    open: boolean;
    component: PayrollComponent | null;
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const form = useForm({
    calculation_method: '' as string,
    global_rate: 0 as number,
    calculation_formula: '' as string,
});

const methodOptions = computed<PayrollCalculationMethodOption[]>(
    () => props.component?.allowed_calculation_methods ?? [],
);

const selectedOption = computed(() =>
    methodOptions.value.find((option) => option.value === form.calculation_method) ?? null,
);

const isCustomFormula = computed(() => form.calculation_method === 'custom_formula');

const isPercentage = computed(
    () =>
        Boolean(selectedOption.value?.is_percentage_rate) ||
        isPercentageOfBasicCalculation(form.calculation_method),
);

const rateLabel = computed(
    () => selectedOption.value?.amount_label ?? props.component?.amount_label ?? 'Rate',
);

const formulaVariableOptions = computed(() => {
    const options = props.component?.formula_variable_options;
    if (options?.length) {
        return options;
    }

    const variables = props.component?.formula_variables ?? [
        'absent_days',
        'present_days',
        'late_minutes',
        'basic_salary',
        'hours_worked',
        'additional_hours_worked',
        'working_days',
        'total_days_of_payroll',
    ];

    return variables.map((value) => ({ value, label: value }));
});

watch(
    () => [props.open, props.component] as const,
    ([open, component]) => {
        if (!open || !component) {
            return;
        }

        form.clearErrors();
        form.calculation_method = component.calculation_method;
        form.global_rate = Number(component.global_rate ?? 0);
        form.calculation_formula = component.calculation_formula ?? '';
    },
    { immediate: true },
);

function submit() {
    if (!props.component?.id) {
        return;
    }

    form.put(`/payroll-structure/components/${props.component.id}/global-rate`, {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <UiModal
        :open="open"
        :title="component ? `Configure ${component.name}` : 'Configure company rate'"
        description="Choose how this deduction is calculated. The value applies to every employee."
        @close="emit('close')"
    >
        <form v-if="component" class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Calculation method
                </label>
                <select
                    v-model="form.calculation_method"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                >
                    <option v-for="option in methodOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
                <p v-if="form.errors.calculation_method" class="mt-1 text-sm text-red-600">
                    {{ form.errors.calculation_method }}
                </p>
            </div>

            <PayrollFormulaBuilder
                v-if="isCustomFormula"
                v-model="form.calculation_formula"
                :variables="formulaVariableOptions"
                :error="form.errors.calculation_formula"
            />

            <template v-else>
                <UiInput
                    v-model.number="form.global_rate"
                    :label="rateLabel"
                    type="number"
                    min="0"
                    :max="isPercentage ? 100 : undefined"
                    step="0.01"
                    required
                    :error="form.errors.global_rate"
                />

                <p class="text-xs text-slate-500">
                    <template v-if="isPercentage && String(form.calculation_method).includes('late')">
                        Example: 0.01% of basic 20,000 with 30 late minutes → 60 deduction.
                    </template>
                    <template v-else-if="isPercentage">
                        Example: 5% of basic 20,000 with 2 absent days → 2,000 deduction.
                    </template>
                    <template v-else-if="String(form.calculation_method).includes('late')">
                        Example: rate 2 with 30 late minutes → 60 deduction.
                    </template>
                    <template v-else>
                        Example: rate 500 with 2 absent days → 1,000 deduction.
                    </template>
                </p>
            </template>

            <div class="flex justify-end gap-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" :disabled="form.processing">Save</UiButton>
            </div>
        </form>
    </UiModal>
</template>
