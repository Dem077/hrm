<script setup lang="ts">
import { computed } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import {
    amountFieldLabel,
    componentToPayrollItem,
    groupPayrollItems,
    updatePayrollItem,
    usesGlobalRateCalculation,
} from '@/lib/payroll';
import type { DesignationPayrollItem, LoanBankOption, PayrollComponent } from '@/types/payroll';

const props = defineProps<{
    items: DesignationPayrollItem[];
    components: PayrollComponent[];
    loanBanks: LoanBankOption[];
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:items': [items: DesignationPayrollItem[]];
}>();

const groups = computed(() => groupPayrollItems(props.items));

const optionalComponents = computed(() =>
    props.components.filter(
        (component) =>
            !component.is_mandatory &&
            component.is_active &&
            !usesGlobalRateCalculation(component.calculation_method),
    ),
);

const availableOptionalComponents = computed(() => {
    const assignedIds = new Set(props.items.map((item) => item.payroll_component_id));

    return optionalComponents.value.filter((component) => !assignedIds.has(component.id!));
});

function itemIndex(item: DesignationPayrollItem) {
    return props.items.findIndex((row) => row.payroll_component_id === item.payroll_component_id);
}

function updateAmount(item: DesignationPayrollItem, amount: number) {
    emit('update:items', updatePayrollItem(props.items, item, { amount }));
}

function updateLoanMonths(item: DesignationPayrollItem, loanMonths: number) {
    emit('update:items', updatePayrollItem(props.items, item, { loan_months: loanMonths }));
}

function updateLoanBank(item: DesignationPayrollItem, loanBank: string) {
    const bank = props.loanBanks.find((option) => option.value === loanBank);

    emit(
        'update:items',
        updatePayrollItem(props.items, item, {
            loan_bank: loanBank,
            loan_bank_label: bank?.label ?? loanBank,
        }),
    );
}

function addOptionalComponent(componentId: number) {
    const component = props.components.find((row) => row.id === componentId);

    if (!component) {
        return;
    }

    const defaultBank = props.loanBanks[0] ?? null;

    emit('update:items', [...props.items, componentToPayrollItem(component, 0, defaultBank)]);
}

function removeItem(item: DesignationPayrollItem) {
    if (item.is_mandatory) {
        return;
    }

    emit(
        'update:items',
        props.items.filter((row) => row.payroll_component_id !== item.payroll_component_id),
    );
}
</script>

<template>
    <div>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Payroll structure</h3>
            <select
                v-if="availableOptionalComponents.length > 0"
                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                @change="addOptionalComponent(Number(($event.target as HTMLSelectElement).value)); ($event.target as HTMLSelectElement).value = ''"
            >
                <option value="">Add optional component…</option>
                <option v-for="component in availableOptionalComponents" :key="component.id!" :value="component.id!">
                    {{ component.name }} ({{ component.type_label ?? component.type }})
                </option>
            </select>
        </div>

        <p v-if="errors.items" class="mb-3 text-sm text-red-600">{{ errors.items }}</p>

        <div class="space-y-4">
            <section v-if="groups.mandatory.length > 0" class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400">
                    Mandatory
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="item in groups.mandatory" :key="item.payroll_component_id" class="grid gap-3 px-4 py-3 md:grid-cols-[1fr_12rem] md:items-start">
                        <div>
                            <p class="font-medium text-slate-900 dark:text-white">{{ item.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ item.type_label ?? item.type }} · {{ item.calculation_method_label ?? item.calculation_method }}
                            </p>
                        </div>
                        <UiInput
                            :model-value="item.amount"
                            :label="amountFieldLabel(item)"
                            type="number"
                            min="0"
                            step="0.01"
                            :error="errors[`items.${itemIndex(item)}.amount`]"
                            @update:model-value="updateAmount(item, Number($event))"
                        />
                    </div>
                </div>
            </section>

            <section v-if="groups.fixed_additions.length > 0" class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400">
                    Fixed additions
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="item in groups.fixed_additions" :key="item.payroll_component_id" class="grid gap-3 px-4 py-3 md:grid-cols-[1fr_12rem_auto] md:items-start">
                        <div>
                            <p class="font-medium text-slate-900 dark:text-white">{{ item.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.type_label ?? item.type }}</p>
                        </div>
                        <UiInput
                            :model-value="item.amount"
                            label="Amount"
                            type="number"
                            min="0"
                            step="0.01"
                            :error="errors[`items.${itemIndex(item)}.amount`]"
                            @update:model-value="updateAmount(item, Number($event))"
                        />
                        <UiButton type="button" size="sm" variant="ghost" class="self-end" @click="removeItem(item)">Remove</UiButton>
                    </div>
                </div>
            </section>

            <section v-if="groups.attendance_allowance.length > 0" class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400">
                    Attendance allowance rates
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="item in groups.attendance_allowance" :key="item.payroll_component_id" class="grid gap-3 px-4 py-3 md:grid-cols-[1fr_12rem_auto] md:items-start">
                        <div>
                            <p class="font-medium text-slate-900 dark:text-white">{{ item.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{
                                    item.calculation_method === 'hourly'
                                        ? 'Paid as rate × hours worked in the payroll period'
                                        : 'Paid as rate × days attended in the payroll period'
                                }}
                            </p>
                        </div>
                        <UiInput
                            :model-value="item.amount"
                            :label="item.calculation_method === 'hourly' ? 'Rate / hour' : 'Rate / attended day'"
                            type="number"
                            min="0"
                            step="0.01"
                            :error="errors[`items.${itemIndex(item)}.amount`]"
                            @update:model-value="updateAmount(item, Number($event))"
                        />
                        <UiButton
                            v-if="!item.is_mandatory"
                            type="button"
                            size="sm"
                            variant="ghost"
                            class="self-end"
                            @click="removeItem(item)"
                        >
                            Remove
                        </UiButton>
                    </div>
                </div>
            </section>

            <section v-if="groups.company_penalties.length > 0" class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400">
                    Company-wide rates
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="item in groups.company_penalties" :key="item.payroll_component_id" class="px-4 py-3">
                        <p class="font-medium text-slate-900 dark:text-white">{{ item.name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ item.calculation_method_label ?? item.calculation_method }}
                            ·
                            <template v-if="item.calculation_method === 'custom_formula'">
                                <span class="font-mono">{{ item.calculation_formula || 'custom formula' }}</span>
                            </template>
                            <template v-else-if="item.is_percentage_rate || String(item.calculation_method).includes('_of_basic')">
                                {{ item.global_rate ?? item.amount }}% of basic
                            </template>
                            <template v-else>
                                company rate {{ item.global_rate ?? item.amount }}
                            </template>
                            (configure under Payroll components)
                        </p>
                    </div>
                </div>
            </section>

            <section v-if="groups.loans.length > 0" class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400">
                    Loans
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div
                        v-for="item in groups.loans"
                        :key="item.payroll_component_id"
                        class="grid gap-3 px-4 py-3 md:grid-cols-[1fr_repeat(3,minmax(0,9rem))_auto] md:items-start"
                    >
                        <div>
                            <p class="font-medium text-slate-900 dark:text-white">{{ item.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Monthly deduction for the repayment period</p>
                        </div>
                        <UiInput
                            :model-value="item.amount"
                            label="Monthly payment"
                            type="number"
                            min="0"
                            step="0.01"
                            :error="errors[`items.${itemIndex(item)}.amount`]"
                            @update:model-value="updateAmount(item, Number($event))"
                        />
                        <UiInput
                            :model-value="item.loan_months ?? ''"
                            label="Period (months)"
                            type="number"
                            min="1"
                            step="1"
                            :error="errors[`items.${itemIndex(item)}.loan_months`]"
                            @update:model-value="updateLoanMonths(item, Number($event))"
                        />
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Bank</label>
                            <select
                                :value="item.loan_bank ?? ''"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                                @change="updateLoanBank(item, ($event.target as HTMLSelectElement).value)"
                            >
                                <option value="" disabled>Select bank</option>
                                <option v-for="bank in loanBanks" :key="bank.value" :value="bank.value">
                                    {{ bank.label }}
                                </option>
                            </select>
                            <p v-if="errors[`items.${itemIndex(item)}.loan_bank`]" class="mt-1 text-sm text-red-600">
                                {{ errors[`items.${itemIndex(item)}.loan_bank`] }}
                            </p>
                        </div>
                        <UiButton type="button" size="sm" variant="ghost" class="self-end" @click="removeItem(item)">Remove</UiButton>
                    </div>
                </div>
            </section>

            <section v-if="groups.fixed_deductions.length > 0" class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400">
                    Fixed deductions
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="item in groups.fixed_deductions" :key="item.payroll_component_id" class="grid gap-3 px-4 py-3 md:grid-cols-[1fr_12rem_auto] md:items-start">
                        <div>
                            <p class="font-medium text-slate-900 dark:text-white">{{ item.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.type_label ?? item.type }}</p>
                        </div>
                        <UiInput
                            :model-value="item.amount"
                            label="Amount"
                            type="number"
                            min="0"
                            step="0.01"
                            :error="errors[`items.${itemIndex(item)}.amount`]"
                            @update:model-value="updateAmount(item, Number($event))"
                        />
                        <UiButton type="button" size="sm" variant="ghost" class="self-end" @click="removeItem(item)">Remove</UiButton>
                    </div>
                </div>
            </section>

            <p v-if="items.length === 0" class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                No payroll components assigned yet.
            </p>
        </div>
    </div>
</template>
