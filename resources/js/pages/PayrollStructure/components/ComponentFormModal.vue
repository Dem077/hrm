<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import PayrollFormulaBuilder from '@/pages/PayrollStructure/components/PayrollFormulaBuilder.vue';
import type { PayrollComponent } from '@/types/payroll';

const props = defineProps<{
    open: boolean;
    component: PayrollComponent | null;
    emptyComponent: PayrollComponent;
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const form = useForm({
    ...props.emptyComponent,
    calculation_formula: props.emptyComponent.calculation_formula ?? '',
});

const isLoanType = computed(() => form.type === 'loan');
const isCustomFormula = computed(() => form.calculation_method === 'custom_formula');

const formulaVariableOptions = computed(() => {
    const options = props.component?.formula_variable_options ?? props.emptyComponent.formula_variable_options;
    if (options?.length) {
        return options;
    }

    const variables =
        props.component?.formula_variables ??
        props.emptyComponent.formula_variables ?? [
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
        if (!open) {
            return;
        }

        form.clearErrors();

        if (component) {
            form.name = component.name;
            form.code = component.code ?? '';
            form.type = component.type;
            form.calculation_method = component.calculation_method;
            form.calculation_formula = component.calculation_formula ?? '';
            form.is_mandatory = component.is_mandatory;
            form.sort_order = component.sort_order;
            form.is_active = component.is_active;
            return;
        }

        form.reset();
        form.defaults({
            ...props.emptyComponent,
            calculation_formula: props.emptyComponent.calculation_formula ?? '',
        });
    },
    { immediate: true },
);

function submit() {
    if (props.component?.id) {
        form.put(`/payroll-structure/components/${props.component.id}`, {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });

        return;
    }

    form.post('/payroll-structure/components', {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <UiModal
        :open="open"
        :title="component ? 'Edit component' : 'Add component'"
        description="Define how this payroll line is calculated across designations."
        @close="emit('close')"
    >
        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
            <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
            <UiInput v-model="form.code" label="Code" :error="form.errors.code" />
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Type</label>
                <select
                    v-model="form.type"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                >
                    <option value="addition">Addition</option>
                    <option value="deduction">Deduction</option>
                    <option value="loan">Loan</option>
                </select>
                <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
            </div>
            <div v-if="!isLoanType">
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Calculation</label>
                <select
                    v-model="form.calculation_method"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                >
                    <option value="fixed">Fixed amount</option>
                    <option value="daily">Attendance allowance (days attended)</option>
                    <option value="hourly">Attendance allowance (hours worked)</option>
                    <option value="custom_formula">Custom formula</option>
                </select>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Attendance allowance uses a rate based on attended days or worked hours. Custom formula uses attendance and salary variables.
                </p>
                <p v-if="form.errors.calculation_method" class="mt-1 text-sm text-red-600">{{ form.errors.calculation_method }}</p>
            </div>
            <div v-else class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400">
                Loan components use monthly payment, repayment period, and bank details on each designation.
            </div>

            <div v-if="!isLoanType && isCustomFormula" class="md:col-span-2">
                <PayrollFormulaBuilder
                    v-model="form.calculation_formula"
                    :variables="formulaVariableOptions"
                    :error="form.errors.calculation_formula"
                />
            </div>

            <UiInput v-model.number="form.sort_order" label="Sort order" type="number" min="0" :error="form.errors.sort_order" />
            <label v-if="!isLoanType" class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700 dark:text-slate-300">
                <input v-model="form.is_mandatory" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                Mandatory for all designations
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                Active
            </label>
            <div class="flex justify-end gap-2 md:col-span-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" :disabled="form.processing">
                    {{ component ? 'Update component' : 'Create component' }}
                </UiButton>
            </div>
        </form>
    </UiModal>
</template>
