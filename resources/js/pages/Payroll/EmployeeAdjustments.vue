<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
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

type DetailLine = {
    component: string;
    method: string;
    rate: number;
    amount: number;
    type: string;
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
    };
    adjustments: Adjustment[];
    can_edit: boolean;
}>();

const { can } = usePermissions();

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

function formatMoney(value: number): string {
    return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                        <tr v-for="(line, index) in employee.details" :key="`${line.component}-${index}`">
                            <td class="px-3 py-2 font-medium">{{ line.component }}</td>
                            <td class="px-3 py-2 capitalize">{{ line.type }}</td>
                            <td class="px-3 py-2 capitalize">{{ line.method }}</td>
                            <td class="px-3 py-2">{{ formatMoney(line.rate) }}</td>
                            <td class="px-3 py-2 text-right">{{ formatMoney(line.amount) }}</td>
                        </tr>
                        <tr v-if="employee.details.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">
                                No payroll structure components on this employee's grade.
                            </td>
                        </tr>
                    </tbody>
                </table>
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
    </AppLayout>
</template>
