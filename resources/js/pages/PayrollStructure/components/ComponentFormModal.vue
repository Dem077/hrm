<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import PayrollFormulaBuilder from '@/pages/PayrollStructure/components/PayrollFormulaBuilder.vue';
import type { PayrollApplicabilityRule, PayrollComponent } from '@/types/payroll';

type Option = { value: string; label: string };

const props = defineProps<{
    open: boolean;
    component: PayrollComponent | null;
    emptyComponent: PayrollComponent;
    employmentTypes: Option[];
    nationalities: Option[];
    applicabilityFields: Option[];
    applicabilityOperators: Option[];
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const form = useForm({
    ...props.emptyComponent,
    calculation_formula: props.emptyComponent.calculation_formula ?? '',
    applicability_rules: {
        all: [...(props.emptyComponent.applicability_rules?.all ?? [])],
    },
});

const isLoanType = computed(() => form.type === 'loan');
const isCustomFormula = computed(() => form.calculation_method === 'custom_formula');
const showConditions = computed(() => !isLoanType.value && form.is_mandatory);

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
            'gross_salary',
            'total_deductions',
            'net_salary',
            'hours_worked',
            'additional_hours_worked',
            'overtime_hours',
            'working_days',
            'total_days_of_payroll',
        ];

    const labels: Record<string, string> = {
        absent_days: 'Absent days',
        present_days: 'Present days',
        late_minutes: 'Late minutes',
        basic_salary: 'Basic salary',
        gross_salary: 'Gross salary',
        total_deductions: 'Total deductions',
        net_salary: 'Net salary',
        hours_worked: 'Hours worked',
        additional_hours_worked: 'Additional hours worked',
        overtime_hours: 'Overtime approved hours',
        working_days: 'Working days',
        total_days_of_payroll: 'Total days of payroll',
    };

    return variables.map((value) => ({
        value,
        label: labels[value] ?? value,
    }));
});

function emptyRule(): PayrollApplicabilityRule {
    return { field: 'nationality', operator: 'eq', value: '' };
}

function addCondition() {
    form.applicability_rules = {
        all: [...(form.applicability_rules?.all ?? []), emptyRule()],
    };
}

function removeCondition(index: number) {
    form.applicability_rules = {
        all: (form.applicability_rules?.all ?? []).filter((_, i) => i !== index),
    };
}

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
            form.applicability_rules = {
                all: (component.applicability_rules?.all ?? []).map((rule) => ({
                    field: rule.field,
                    operator: rule.operator || 'eq',
                    value: rule.value,
                })),
            };
            form.sort_order = component.sort_order;
            form.is_active = component.is_active;
            return;
        }

        form.reset();
        form.defaults({
            ...props.emptyComponent,
            calculation_formula: props.emptyComponent.calculation_formula ?? '',
            applicability_rules: { all: [] },
        });
    },
    { immediate: true },
);

function submit() {
    const payloadTransform = (data: typeof form) => ({
        ...data,
        applicability_rules: data.is_mandatory
            ? {
                  all: (data.applicability_rules?.all ?? []).filter(
                      (rule) => rule.field && rule.operator && String(rule.value ?? '').trim() !== '',
                  ),
              }
            : { all: [] },
    });

    if (props.component?.id) {
        form.transform(payloadTransform).put(`/payroll-structure/components/${props.component.id}`, {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });

        return;
    }

    form.transform(payloadTransform).post('/payroll-structure/components', {
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
                    System Attendance Allowance with days attended / hours worked uses a rate per designation (Configure method, then set amounts on designation salary structures). Custom formula stays company-wide.
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

            <div v-if="showConditions" class="md:col-span-2 space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Required only when</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            All conditions must match the employee. Leave empty to apply to everyone on the designation.
                        </p>
                    </div>
                    <UiButton type="button" size="sm" variant="secondary" @click="addCondition">Add condition</UiButton>
                </div>

                <div v-if="(form.applicability_rules?.all?.length ?? 0) === 0" class="text-sm text-slate-500">
                    No conditions — mandatory for all employees on each designation.
                </div>

                <div
                    v-for="(rule, index) in form.applicability_rules?.all ?? []"
                    :key="index"
                    class="grid gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-surface-elevated md:grid-cols-[minmax(0,11rem)_minmax(0,9rem)_1fr_auto]"
                >
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Field</label>
                        <select
                            v-model="rule.field"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                            @change="rule.value = ''"
                        >
                            <option v-for="field in applicabilityFields" :key="field.value" :value="field.value">
                                {{ field.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Condition</label>
                        <select
                            v-model="rule.operator"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                        >
                            <option
                                v-for="operator in applicabilityOperators"
                                :key="operator.value"
                                :value="operator.value"
                            >
                                {{ operator.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Value</label>
                        <select
                            v-if="rule.field === 'employment_type'"
                            v-model="rule.value"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                        >
                            <option value="">Select employment type</option>
                            <option v-for="type in employmentTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                        <select
                            v-else-if="rule.field === 'nationality'"
                            v-model="rule.value"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                        >
                            <option value="">Select nationality</option>
                            <option
                                v-if="rule.value && !nationalities.some((item) => item.value === rule.value)"
                                :value="rule.value"
                            >
                                {{ rule.value }} (current)
                            </option>
                            <option v-for="item in nationalities" :key="item.value" :value="item.value">
                                {{ item.label }}
                            </option>
                        </select>
                        <input
                            v-else
                            v-model="rule.value"
                            type="text"
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                        />
                        <p
                            v-if="form.errors[`applicability_rules.all.${index}.value`]"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors[`applicability_rules.all.${index}.value`] }}
                        </p>
                        <p
                            v-else-if="form.errors[`applicability_rules.all.${index}.operator`]"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors[`applicability_rules.all.${index}.operator`] }}
                        </p>
                    </div>
                    <div class="flex items-end">
                        <UiButton type="button" size="sm" variant="ghost" @click="removeCondition(index)">Remove</UiButton>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 md:col-span-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" :disabled="form.processing">
                    {{ component ? 'Update component' : 'Create component' }}
                </UiButton>
            </div>
        </form>
    </UiModal>
</template>
