<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSearchableSelect from '@/components/ui/UiSearchableSelect.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatPayrollMoney } from '@/lib/payroll';
import type { EmployeeLoan, LoanBankOption } from '@/types/payroll';

type EmployeeOption = {
    id: number;
    name: string;
    staff_id: string;
    label: string;
};

const props = defineProps<{
    loans: EmployeeLoan[];
    employees: EmployeeOption[];
    loanBanks: LoanBankOption[];
    emptyLoan: EmployeeLoan;
}>();

const { can } = usePermissions();
const canManage = can('payroll-structure.update');
const editingId = ref<number | null>(null);
const searchText = ref('');

const form = useForm({
    employee_id: props.emptyLoan.employee_id as number | string | null,
    name: props.emptyLoan.name,
    monthly_amount: props.emptyLoan.monthly_amount as number | string,
    loan_months: props.emptyLoan.loan_months as number | string | null,
    loan_bank: props.emptyLoan.loan_bank ?? '',
    start_date: props.emptyLoan.start_date ?? '',
    notes: props.emptyLoan.notes ?? '',
    is_active: props.emptyLoan.is_active,
});

const employeeOptions = computed(() =>
    props.employees.map((employee) => ({
        value: employee.id,
        label: employee.label,
    })),
);

const filteredLoans = computed(() => {
    const needle = searchText.value.trim().toLowerCase();

    if (needle === '') {
        return props.loans;
    }

    return props.loans.filter((loan) => {
        const haystack = [
            loan.name,
            loan.employee?.name,
            loan.employee?.staff_id,
            loan.loan_bank,
            loan.loan_bank_label,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(needle);
    });
});

function resetForm() {
    editingId.value = null;
    form.reset();
    form.employee_id = props.emptyLoan.employee_id;
    form.name = props.emptyLoan.name;
    form.monthly_amount = props.emptyLoan.monthly_amount;
    form.loan_months = props.emptyLoan.loan_months;
    form.loan_bank = props.emptyLoan.loan_bank ?? '';
    form.start_date = props.emptyLoan.start_date ?? '';
    form.notes = props.emptyLoan.notes ?? '';
    form.is_active = props.emptyLoan.is_active;
    form.clearErrors();
}

function editLoan(loan: EmployeeLoan) {
    editingId.value = loan.id;
    form.employee_id = loan.employee_id;
    form.name = loan.name;
    form.monthly_amount = loan.monthly_amount;
    form.loan_months = loan.loan_months;
    form.loan_bank = loan.loan_bank ?? '';
    form.start_date = loan.start_date ?? '';
    form.notes = loan.notes ?? '';
    form.is_active = loan.is_active;
    form.clearErrors();
}

function submit() {
    form.loan_bank = form.loan_bank || null;
    form.loan_months = form.loan_months === '' || form.loan_months === null ? null : form.loan_months;
    form.start_date = form.start_date || null;
    form.notes = form.notes || null;

    if (editingId.value) {
        form.put(`/payroll-structure/loans/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post('/payroll-structure/loans', {
        preserveScroll: true,
        onSuccess: resetForm,
    });
}

function destroyLoan(id: number, name: string) {
    if (confirm(`Delete loan "${name}"? It will no longer be deducted in payroll.`)) {
        router.delete(`/payroll-structure/loans/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Employee Loans" />

    <AppLayout>
        <PageHeader
            title="Employee loans"
            description="Assign monthly loan repayments directly to employees. Remaining period and amount are based on start date and total months."
        />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
            <UiCard padding="none">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Loan records</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ filteredLoans.length }} of {{ loans.length }} shown</p>
                    </div>
                    <div class="w-full max-w-xs">
                        <UiInput v-model="searchText" label="Search" placeholder="Employee, loan, or bank" />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Employee</th>
                                <th class="px-5 py-3.5 font-medium">Loan</th>
                                <th class="px-5 py-3.5 font-medium">Monthly</th>
                                <th class="px-5 py-3.5 font-medium">Period</th>
                                <th class="px-5 py-3.5 font-medium">Remaining</th>
                                <th class="px-5 py-3.5 font-medium">Bank</th>
                                <th class="px-5 py-3.5 font-medium">Status</th>
                                <th v-if="canManage" class="px-5 py-3.5 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="loan in filteredLoans" :key="loan.id!">
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ loan.employee?.name }}</p>
                                    <p class="text-xs text-slate-500">{{ loan.employee?.staff_id }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-700 dark:text-slate-300">
                                    <p>{{ loan.name }}</p>
                                    <p v-if="loan.start_date" class="text-xs text-slate-500">From {{ loan.start_date }}</p>
                                </td>
                                <td class="px-5 py-4 font-medium text-slate-900 dark:text-white">
                                    {{ formatPayrollMoney(Number(loan.monthly_amount)) }}
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    <template v-if="loan.loan_months">
                                        <p>{{ loan.loan_months }} mo</p>
                                        <p v-if="loan.total_amount != null" class="text-xs text-slate-500">
                                            Total {{ formatPayrollMoney(loan.total_amount) }}
                                        </p>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-5 py-4 text-slate-700 dark:text-slate-300">
                                    <template v-if="loan.remaining_months != null">
                                        <p class="font-medium">{{ loan.remaining_months }} mo left</p>
                                        <p v-if="loan.remaining_amount != null" class="text-xs text-slate-500">
                                            {{ formatPayrollMoney(loan.remaining_amount) }}
                                        </p>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ loan.loan_bank_label ?? loan.loan_bank ?? '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="
                                            loan.is_active
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                : 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-400'
                                        "
                                    >
                                        {{ loan.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td v-if="canManage" class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <UiButton size="sm" variant="ghost" @click="editLoan(loan)">Edit</UiButton>
                                        <UiButton size="sm" variant="danger" @click="destroyLoan(loan.id!, loan.name)">
                                            Delete
                                        </UiButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredLoans.length === 0">
                                <td :colspan="canManage ? 8 : 7" class="px-5 py-8 text-center text-slate-500">
                                    No employee loans found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>

            <UiCard
                v-if="canManage"
                :title="editingId ? 'Edit loan' : 'Add loan'"
                description="Link a monthly repayment amount to a specific employee."
            >
                <form class="space-y-4" @submit.prevent="submit">
                    <UiSearchableSelect
                        label="Employee"
                        :model-value="form.employee_id"
                        :options="employeeOptions"
                        placeholder="Search employee…"
                        :error="form.errors.employee_id"
                        @update:model-value="form.employee_id = $event"
                    />
                    <UiInput v-model="form.name" label="Loan name" required :error="form.errors.name" placeholder="Staff loan" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <UiInput
                            v-model="form.monthly_amount"
                            label="Monthly amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                            :error="form.errors.monthly_amount"
                        />
                        <UiInput
                            v-model="form.loan_months"
                            label="Period (months)"
                            type="number"
                            min="1"
                            step="1"
                            :error="form.errors.loan_months"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <UiSelect
                            :model-value="form.loan_bank"
                            label="Bank"
                            :error="form.errors.loan_bank"
                            @update:model-value="form.loan_bank = ($event as string) ?? ''"
                        >
                            <option value="">No bank</option>
                            <option v-for="bank in loanBanks" :key="bank.value" :value="bank.value">
                                {{ bank.label }}
                            </option>
                        </UiSelect>
                        <UiInput v-model="form.start_date" label="Start date" type="date" :error="form.errors.start_date" />
                    </div>
                    <UiInput v-model="form.notes" label="Notes" :error="form.errors.notes" />
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                        Loan is active (deduct in payroll)
                    </label>

                    <div class="flex flex-wrap gap-2">
                        <UiButton type="submit" variant="primary" :disabled="form.processing">
                            {{ editingId ? 'Save changes' : 'Add loan' }}
                        </UiButton>
                        <UiButton v-if="editingId" type="button" variant="ghost" @click="resetForm">Cancel</UiButton>
                    </div>
                </form>
            </UiCard>
        </div>
    </AppLayout>
</template>
