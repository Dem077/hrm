<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';

type Adjustment = {
    id: number;
    title: string;
    type: 'addition' | 'deduction' | string;
    amount: number;
    remarks: string | null;
    created_at: string | null;
};

type FormulaVariables = Record<string, number | string | null>;

type CalculationInput = {
    key: string;
    label: string;
    value: number | string;
};

type DetailLine = {
    component: string;
    method: string;
    method_label?: string | null;
    rate: number;
    amount: number;
    type: string;
    basic_salary?: number | null;
    formula?: string | null;
    formula_variables?: FormulaVariables | null;
    calculation_inputs?: CalculationInput[] | null;
    calculation_summary?: string | null;
};

type AttendanceSummary = {
    days_attended?: number | null;
    hours_worked?: number | null;
    late_minutes?: number | null;
    absent_days?: number | null;
    present_days?: number | null;
    formula_variables?: FormulaVariables | null;
};

const props = defineProps<{
    run: {
        id: number;
        reference_no: string;
        period_label: string;
        status: 'draft' | 'processed' | 'finalised';
        status_label: string;
    };
    employee: {
        employee_id: number;
        staff_id: string | null;
        employee_name: string;
        national_id: string | null;
        department: string | null;
        designation: string | null;
        days_attended: number;
        hours_worked: number;
        base_gross: number;
        base_deductions: number;
        base_net: number;
        manual_additions: number;
        manual_deductions: number;
        gross: number;
        deductions: number;
        net: number;
        details: DetailLine[];
        attendance_summary?: AttendanceSummary;
    };
    adjustments: Adjustment[];
    can_edit: boolean;
}>();

const { can } = usePermissions();

const selectedDetail = ref<DetailLine | null>(null);
const detailModalOpen = ref(false);

const form = useForm({
    type: 'addition' as 'addition' | 'deduction',
    title: '',
    amount: '',
    remarks: '',
});

const additionDetails = computed(() => props.employee.details.filter((line) => line.type === 'addition'));
const deductionDetails = computed(() =>
    props.employee.details.filter((line) => line.type === 'deduction' || line.type === 'loan'),
);

const variableLabels: Record<string, string> = {
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

function formatMoney(value: number): string {
    return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(value: number): string {
    return Number.isInteger(value)
        ? value.toLocaleString()
        : value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function deriveQuantity(amount: number, divisor: number): number {
    if (divisor <= 0 || amount <= 0) {
        return 0;
    }

    return Math.round((amount / divisor) * 100) / 100;
}

function readInputValue(inputs: CalculationInput[] | null | undefined, key: string): number | null {
    const found = inputs?.find((input) => input.key === key);
    if (!found || found.value === null || found.value === undefined || found.value === '') {
        return null;
    }

    const numeric = Number(found.value);
    return Number.isFinite(numeric) ? numeric : null;
}

function resolveLateMinutes(detail: DetailLine, stored: number): number {
    if (stored > 0) {
        return stored;
    }

    const amount = Number(detail.amount ?? 0);
    const rate = Number(detail.rate ?? 0);
    const basicSalary = Number(detail.basic_salary ?? 0);

    if (detail.method === 'per_late_minute' && rate > 0) {
        return deriveQuantity(amount, rate);
    }

    if (detail.method === 'per_late_minute_of_basic' && rate > 0 && basicSalary > 0) {
        return deriveQuantity(amount, (basicSalary * rate) / 100);
    }

    return stored;
}

function resolveAbsentDays(detail: DetailLine, stored: number): number {
    if (stored > 0) {
        return stored;
    }

    const amount = Number(detail.amount ?? 0);
    const rate = Number(detail.rate ?? 0);
    const basicSalary = Number(detail.basic_salary ?? 0);

    if (detail.method === 'per_absent_day' && rate > 0) {
        return deriveQuantity(amount, rate);
    }

    if (detail.method === 'per_absent_day_of_basic' && rate > 0 && basicSalary > 0) {
        return deriveQuantity(amount, (basicSalary * rate) / 100);
    }

    return stored;
}

function resolveHoursWorked(detail: DetailLine, stored: number): number {
    if (stored > 0) {
        return stored;
    }

    const amount = Number(detail.amount ?? 0);
    const rate = Number(detail.rate ?? 0);

    if (detail.method === 'hourly' && rate > 0) {
        return deriveQuantity(amount, rate);
    }

    return stored;
}

function resolveDaysAttended(detail: DetailLine, stored: number): number {
    if (stored > 0) {
        return stored;
    }

    const amount = Number(detail.amount ?? 0);
    const rate = Number(detail.rate ?? 0);

    if (detail.method === 'daily' && rate > 0) {
        return deriveQuantity(amount, rate);
    }

    return stored;
}

const selectedFormulaVariables = computed(() => {
    const detail = selectedDetail.value;
    const vars = detail?.formula_variables;
    if (!detail || !vars) {
        return [] as Array<{ key: string; label: string; value: string }>;
    }

    const formula = String(detail.formula ?? '');
    const entries = Object.entries(vars).filter(([key]) => {
        if (!formula) {
            return true;
        }

        return new RegExp(`\\b${key}\\b`).test(formula);
    });

    return entries.map(([key, value]) => ({
        key,
        label: variableLabels[key] ?? key,
        value: typeof value === 'number' ? formatNumber(value) : String(value ?? '—'),
    }));
});

const selectedCalculationInputs = computed(() => {
    const detail = selectedDetail.value;
    if (!detail) {
        return [] as Array<{ key: string; label: string; value: string }>;
    }

    const summary = props.employee.attendance_summary ?? {};
    const summaryVars = (summary.formula_variables ?? {}) as FormulaVariables;

    const storedLate =
        readInputValue(detail.calculation_inputs, 'late_minutes') ??
        Number(summary.late_minutes ?? summaryVars.late_minutes ?? 0);
    const storedAbsent =
        readInputValue(detail.calculation_inputs, 'absent_days') ??
        Number(summary.absent_days ?? summaryVars.absent_days ?? 0);
    const storedHours =
        readInputValue(detail.calculation_inputs, 'hours_worked') ??
        Number(summary.hours_worked ?? summaryVars.hours_worked ?? props.employee.hours_worked ?? 0);
    const storedDays =
        readInputValue(detail.calculation_inputs, 'days_attended') ??
        Number(summary.days_attended ?? summaryVars.present_days ?? props.employee.days_attended ?? 0);
    const basicSalary =
        readInputValue(detail.calculation_inputs, 'basic_salary') ??
        Number(detail.basic_salary ?? summaryVars.basic_salary ?? 0);

    const lateMinutes = resolveLateMinutes(detail, storedLate);
    const absentDays = resolveAbsentDays(detail, storedAbsent);
    const hoursWorked = resolveHoursWorked(detail, storedHours);
    const daysAttended = resolveDaysAttended(detail, storedDays);

    switch (detail.method) {
        case 'fixed':
            return [{ key: 'rate', label: 'Fixed amount', value: formatNumber(detail.rate) }];
        case 'daily':
            return [
                { key: 'rate', label: 'Rate / attended day', value: formatNumber(detail.rate) },
                { key: 'days_attended', label: 'Days attended', value: formatNumber(daysAttended) },
            ];
        case 'hourly':
            return [
                { key: 'rate', label: 'Rate / hour', value: formatNumber(detail.rate) },
                { key: 'hours_worked', label: 'Hours worked', value: formatNumber(hoursWorked) },
            ];
        case 'per_late_minute':
            return [
                { key: 'rate', label: 'Rate / late minute', value: formatNumber(detail.rate) },
                { key: 'late_minutes', label: 'Late minutes', value: formatNumber(lateMinutes) },
            ];
        case 'per_late_minute_of_basic':
            return [
                { key: 'basic_salary', label: 'Basic salary', value: formatNumber(basicSalary) },
                { key: 'rate', label: '% of basic / late minute', value: formatNumber(detail.rate) },
                { key: 'late_minutes', label: 'Late minutes', value: formatNumber(lateMinutes) },
            ];
        case 'per_absent_day':
            return [
                { key: 'rate', label: 'Rate / absent day', value: formatNumber(detail.rate) },
                { key: 'absent_days', label: 'Absent days', value: formatNumber(absentDays) },
            ];
        case 'per_absent_day_of_basic':
            return [
                { key: 'basic_salary', label: 'Basic salary', value: formatNumber(basicSalary) },
                { key: 'rate', label: '% of basic / absent day', value: formatNumber(detail.rate) },
                { key: 'absent_days', label: 'Absent days', value: formatNumber(absentDays) },
            ];
        case 'per_overtime_hour': {
            const overtimeHours =
                readInputValue(detail.calculation_inputs, 'overtime_hours') ??
                Number(summaryVars.overtime_hours ?? 0);

            return [
                { key: 'rate', label: 'Rate / overtime approved hours', value: formatNumber(detail.rate) },
                { key: 'overtime_hours', label: 'Overtime approved hours', value: formatNumber(overtimeHours) },
            ];
        }
        case 'per_overtime_hour_of_basic': {
            const overtimeHours =
                readInputValue(detail.calculation_inputs, 'overtime_hours') ??
                Number(summaryVars.overtime_hours ?? 0);

            return [
                { key: 'basic_salary', label: 'Basic salary', value: formatNumber(basicSalary) },
                { key: 'rate', label: '% of basic / overtime approved hours', value: formatNumber(detail.rate) },
                { key: 'overtime_hours', label: 'Overtime approved hours', value: formatNumber(overtimeHours) },
            ];
        }
        case 'custom_formula':
            return selectedFormulaVariables.value;
        default:
            return [{ key: 'rate', label: 'Rate', value: formatNumber(detail.rate) }];
    }
});

const selectedCalculationSummary = computed(() => {
    const detail = selectedDetail.value;
    if (!detail) {
        return '';
    }

    const inputs = selectedCalculationInputs.value;
    const amount = formatMoney(detail.amount);

    if (detail.method === 'hourly') {
        const hours = inputs.find((input) => input.key === 'hours_worked')?.value ?? '0';
        return `${formatMoney(detail.rate)} × ${hours} hours worked = ${amount}`;
    }

    if (detail.method === 'daily') {
        const days = inputs.find((input) => input.key === 'days_attended')?.value ?? '0';
        return `${formatMoney(detail.rate)} × ${days} days attended = ${amount}`;
    }

    if (detail.method === 'per_late_minute') {
        const late = inputs.find((input) => input.key === 'late_minutes')?.value ?? '0';
        return `${formatMoney(detail.rate)} × ${late} late minutes = ${amount}`;
    }

    if (detail.method === 'per_late_minute_of_basic') {
        const late = inputs.find((input) => input.key === 'late_minutes')?.value ?? '0';
        const basic =
            inputs.find((input) => input.key === 'basic_salary')?.value ??
            formatMoney(Number(detail.basic_salary ?? 0));
        return `(${basic} × ${formatNumber(detail.rate)}%) × ${late} late minutes = ${amount}`;
    }

    if (detail.method === 'per_absent_day') {
        const absent = inputs.find((input) => input.key === 'absent_days')?.value ?? '0';
        return `${formatMoney(detail.rate)} × ${absent} absent days = ${amount}`;
    }

    if (detail.method === 'per_absent_day_of_basic') {
        const absent = inputs.find((input) => input.key === 'absent_days')?.value ?? '0';
        const basic =
            inputs.find((input) => input.key === 'basic_salary')?.value ??
            formatMoney(Number(detail.basic_salary ?? 0));
        return `(${basic} × ${formatNumber(detail.rate)}%) × ${absent} absent days = ${amount}`;
    }

    if (detail.method === 'per_overtime_hour') {
        const hours = inputs.find((input) => input.key === 'overtime_hours')?.value ?? '0';
        return `${formatMoney(detail.rate)} × ${hours} overtime approved hours = ${amount}`;
    }

    if (detail.method === 'per_overtime_hour_of_basic') {
        const hours = inputs.find((input) => input.key === 'overtime_hours')?.value ?? '0';
        const basic =
            inputs.find((input) => input.key === 'basic_salary')?.value ??
            formatMoney(Number(detail.basic_salary ?? 0));
        return `(${basic} × ${formatNumber(detail.rate)}%) × ${hours} overtime approved hours = ${amount}`;
    }

    if (detail.calculation_summary) {
        return detail.calculation_summary;
    }

    if (detail.method === 'custom_formula' && detail.formula) {
        return `Formula (${detail.formula}) = ${amount}`;
    }

    return `${methodLabel(detail)} → ${amount}`;
});

function methodLabel(line: DetailLine): string {
    return line.method_label ?? line.method.replaceAll('_', ' ');
}

function openDetail(line: DetailLine): void {
    selectedDetail.value = line;
    detailModalOpen.value = true;
}

function closeDetail(): void {
    detailModalOpen.value = false;
    selectedDetail.value = null;
}

function submit(): void {
    form.post(`/payroll/${props.run.id}/employees/${props.employee.employee_id}/adjustments`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'amount', 'remarks');
            form.type = 'addition';
        },
    });
}

function removeAdjustment(adjustment: Adjustment): void {
    if (!confirm(`Remove adjustment "${adjustment.title}"?`)) {
        return;
    }

    router.delete(`/payroll/${props.run.id}/adjustments/${adjustment.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Adjustments - ${employee.employee_name}`" />

    <AppLayout>
        <PageHeader
            :title="employee.employee_name"
            :description="`${employee.staff_id ?? '-'} | ${run.reference_no} | ${run.period_label}`"
        >
            <template #actions>
                <UiButton :href="`/payroll/${run.id}`" variant="ghost">Back to payroll</UiButton>
                <UiButton
                    :href="`/payroll/${run.id}/employees/${employee.employee_id}/attendance`"
                    variant="secondary"
                >
                    Attendance
                </UiButton>
            </template>
        </PageHeader>

        <UiCard
            title="Payroll figures"
            :description="`${employee.department ?? 'No org unit'} | ${employee.designation ?? 'No grade'} | ${employee.days_attended} days / ${employee.hours_worked} hrs`"
        >
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Base gross</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {{ formatMoney(employee.base_gross) }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Base deductions</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {{ formatMoney(employee.base_deductions) }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Manual adjustments</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        +{{ formatMoney(employee.manual_additions) }} / -{{ formatMoney(employee.manual_deductions) }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Final net</p>
                    <p class="mt-1 text-lg font-semibold text-brand-700 dark:text-brand-300">
                        {{ formatMoney(employee.net) }}
                    </p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-3 py-2">Component</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Method</th>
                            <th class="px-3 py-2">Rate</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="(line, index) in employee.details"
                            :key="`${line.component}-${index}`"
                            class="cursor-pointer transition hover:bg-slate-50 dark:hover:bg-surface-elevated/70"
                            @click="openDetail(line)"
                        >
                            <td class="px-3 py-2">
                                <button
                                    type="button"
                                    class="text-left font-medium text-brand-700 hover:underline dark:text-brand-300"
                                >
                                    {{ line.component }}
                                </button>
                            </td>
                            <td class="px-3 py-2 capitalize">{{ line.type }}</td>
                            <td class="px-3 py-2 capitalize">{{ methodLabel(line) }}</td>
                            <td class="px-3 py-2">
                                <span v-if="line.method === 'custom_formula'" class="text-slate-500">—</span>
                                <span v-else>{{ formatMoney(line.rate) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-medium">{{ formatMoney(line.amount) }}</td>
                        </tr>
                        <tr v-if="employee.details.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">
                                No payroll structure components on this employee's grade.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-2 text-xs text-slate-500">Click a component to view how the amount was calculated.</p>
            </div>

            <dl class="mt-4 grid gap-2 border-t border-slate-200 pt-4 text-sm dark:border-slate-700 sm:grid-cols-2 lg:grid-cols-3">
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-slate-500">Structure additions</dt>
                    <dd class="font-medium">{{ formatMoney(additionDetails.reduce((sum, line) => sum + line.amount, 0)) }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-slate-500">Structure deductions</dt>
                    <dd class="font-medium">{{ formatMoney(deductionDetails.reduce((sum, line) => sum + line.amount, 0)) }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-slate-500">Base net</dt>
                    <dd class="font-medium">{{ formatMoney(employee.base_net) }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-slate-500">Final gross</dt>
                    <dd class="font-medium">{{ formatMoney(employee.gross) }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-slate-500">Final deductions</dt>
                    <dd class="font-medium">{{ formatMoney(employee.deductions) }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-slate-500">Final net</dt>
                    <dd class="font-semibold text-brand-700 dark:text-brand-300">{{ formatMoney(employee.net) }}</dd>
                </div>
            </dl>
        </UiCard>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <UiCard
                title="Add adjustment"
                description="Enter the kind of adjustment (for example overtime bonus or late penalty), amount, and type."
            >
                <form v-if="can_edit && can('payroll.adjust')" class="space-y-4" @submit.prevent="submit">
                    <UiSelect v-model="form.type" label="Type" :error="form.errors.type">
                        <option value="addition">Addition</option>
                        <option value="deduction">Deduction</option>
                    </UiSelect>

                    <UiInput
                        v-model="form.title"
                        label="Adjustment kind"
                        placeholder="e.g. Overtime bonus, Transport, Late penalty"
                        :error="form.errors.title"
                    />

                    <UiInput
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        label="Amount"
                        :error="form.errors.amount"
                    />

                    <UiInput
                        v-model="form.remarks"
                        label="Remarks (optional)"
                        placeholder="Extra notes"
                        :error="form.errors.remarks"
                    />

                    <p v-if="form.errors.run" class="text-xs text-red-600 dark:text-red-400">{{ form.errors.run }}</p>

                    <div class="flex justify-end">
                        <UiButton type="submit" variant="primary" :disabled="form.processing">
                            Add Adjustment
                        </UiButton>
                    </div>
                </form>
                <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                    This payroll is read-only. Reopen a finalised run to change adjustments.
                </p>
            </UiCard>

            <UiCard
                title="Manual adjustments"
                :description="`Additions ${formatMoney(employee.manual_additions)} | Deductions ${formatMoney(employee.manual_deductions)}`"
            >
                <div class="space-y-3">
                    <div
                        v-for="adjustment in adjustments"
                        :key="adjustment.id"
                        class="rounded-xl border border-slate-200 px-3 py-3 text-sm dark:border-slate-700"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ adjustment.title }}</p>
                                <p class="text-xs capitalize text-slate-500">
                                    {{ adjustment.type }} | {{ formatMoney(adjustment.amount) }}
                                </p>
                                <p v-if="adjustment.remarks" class="mt-1 text-xs text-slate-500">{{ adjustment.remarks }}</p>
                            </div>
                            <UiButton
                                v-if="can_edit && can('payroll.adjust')"
                                size="sm"
                                variant="danger"
                                @click="removeAdjustment(adjustment)"
                            >
                                Remove
                            </UiButton>
                        </div>
                    </div>
                    <p v-if="adjustments.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
                        No adjustments yet for this employee.
                    </p>
                </div>
            </UiCard>
        </div>

        <UiModal
            :open="detailModalOpen"
            :title="selectedDetail ? selectedDetail.component : 'Calculation details'"
            description="How this payroll component amount was calculated for this employee."
            @close="closeDetail"
        >
            <div v-if="selectedDetail" class="space-y-4 text-sm">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Type</dt>
                        <dd class="mt-1 capitalize font-medium text-slate-900 dark:text-slate-100">
                            {{ selectedDetail.type }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Method</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">
                            {{ methodLabel(selectedDetail) }}
                        </dd>
                    </div>
                    <div v-if="selectedDetail.method !== 'custom_formula'">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Rate</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">
                            {{ formatMoney(selectedDetail.rate) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Amount</dt>
                        <dd class="mt-1 text-lg font-semibold text-brand-700 dark:text-brand-300">
                            {{ formatMoney(selectedDetail.amount) }}
                        </dd>
                    </div>
                </dl>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-surface-elevated">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Calculation</p>
                    <p class="mt-1 font-mono text-sm text-slate-800 dark:text-slate-100">
                        {{ selectedCalculationSummary }}
                    </p>
                </div>

                <div v-if="selectedDetail.formula">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Formula</p>
                    <p class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-800 dark:border-slate-700 dark:bg-surface dark:text-slate-100">
                        {{ selectedDetail.formula }}
                    </p>
                </div>

                <div v-if="selectedCalculationInputs.length">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Values used
                    </p>
                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-surface-elevated">
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-3 py-2">Input</th>
                                    <th class="px-3 py-2 text-right">Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="input in selectedCalculationInputs" :key="input.key">
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ input.label }}</span>
                                        <span class="mt-0.5 block font-mono text-[11px] text-slate-500">{{ input.key }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono font-medium">{{ input.value }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end">
                    <UiButton type="button" variant="secondary" @click="closeDetail">Close</UiButton>
                </div>
            </div>
        </UiModal>
    </AppLayout>
</template>
