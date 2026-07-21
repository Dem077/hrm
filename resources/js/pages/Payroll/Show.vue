<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';

type Row = {
    employee_id: number;
    staff_id: string | null;
    employee_name: string;
    national_id: string | null;
    bank_name: string | null;
    account_name: string | null;
    account_no: string | null;
    department: string | null;
    designation: string | null;
    days_attended: number;
    hours_worked: number;
    manual_additions: number;
    manual_deductions: number;
    gross: number;
    deductions: number;
    net: number;
};

type BankTotal = {
    bank: string;
    employee_count: number;
    gross: number;
    deductions: number;
    net: number;
};

const props = defineProps<{
    selectedRun: {
        id: number;
        status: 'draft' | 'processed' | 'finalised';
        status_label: string;
        reference_no: string;
        period_label: string;
        period_from: string;
        period_to: string;
        totals: { gross: number; deductions: number; net: number };
        audit_logs: Array<{
            id: number;
            event_type: string;
            performed_by: string | null;
            password_confirmed: boolean;
            created_at: string | null;
            context: Record<string, unknown> | null;
        }>;
    };
    rows: Row[];
    bankTotals: BankTotal[];
    filters: { q: string };
}>();

const { can } = usePermissions();
const page = usePage<{ errors: Record<string, string> }>();
const showReopenModal = ref(false);
const searchText = ref(props.filters.q ?? '');

const reopenForm = reactive({
    password: '',
    reason: '',
});

const bankGroups = computed(() => {
    const map = new Map<string, Row[]>();
    props.rows.forEach((row) => {
        const key = row.bank_name?.trim() || 'No bank';
        const current = map.get(key) ?? [];
        current.push(row);
        map.set(key, current);
    });

    return Array.from(map.entries())
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([bank, rows]) => {
            const totals = {
                gross: rows.reduce((sum, row) => sum + row.gross, 0),
                deductions: rows.reduce((sum, row) => sum + row.deductions, 0),
                net: rows.reduce((sum, row) => sum + row.net, 0),
            };

            return { bank, rows, totals };
        });
});

function statusColor(status: typeof props.selectedRun.status): string {
    if (status === 'draft') return 'warning';
    if (status === 'processed') return 'info';
    return 'success';
}

function formatMoney(value: number): string {
    return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applySearch(): void {
    router.get(
        `/payroll/${props.selectedRun.id}`,
        { q: searchText.value || undefined },
        { preserveState: true, replace: true },
    );
}

function processRun(): void {
    router.post(`/payroll/${props.selectedRun.id}/process`);
}

function rerunPayroll(): void {
    if (!confirm('Refresh payroll data from the latest attendance and salary structure? Manual adjustments will be kept.')) {
        return;
    }

    router.post(`/payroll/${props.selectedRun.id}/rerun`);
}

function finalizeRun(): void {
    router.post(`/payroll/${props.selectedRun.id}/finalize`);
}

function reopenRun(): void {
    router.post(`/payroll/${props.selectedRun.id}/reopen`, reopenForm, {
        onSuccess: () => {
            showReopenModal.value = false;
            reopenForm.password = '';
            reopenForm.reason = '';
        },
    });
}

function deleteRun(): void {
    if (!confirm(`Delete draft payroll ${props.selectedRun.reference_no}? This cannot be undone.`)) {
        return;
    }

    router.delete(`/payroll/${props.selectedRun.id}`);
}

const exportUrl = computed(() => `/payroll/${props.selectedRun.id}/export`);
</script>

<template>
    <Head :title="`Payroll ${selectedRun.reference_no}`" />

    <AppLayout>
        <PageHeader
            :title="selectedRun.reference_no"
            :description="`${selectedRun.period_label} | ${selectedRun.status_label}`"
        >
            <template #actions>
                <UiButton href="/payroll" variant="ghost">Back to runs</UiButton>
                <UiBadge :label="selectedRun.status_label" :color="statusColor(selectedRun.status)" />
                <UiButton v-if="can('payroll.export')" :href="exportUrl" external variant="secondary">
                    Export List
                </UiButton>
                <UiButton
                    v-if="can('payroll.process') && selectedRun.status === 'draft'"
                    variant="secondary"
                    @click="processRun"
                >
                    Process Payroll
                </UiButton>
                <UiButton
                    v-if="can('payroll.process') && (selectedRun.status === 'draft' || selectedRun.status === 'processed')"
                    variant="ghost"
                    @click="rerunPayroll"
                >
                    Rerun Payroll
                </UiButton>
                <UiButton
                    v-if="can('payroll.delete') && selectedRun.status === 'draft'"
                    variant="danger"
                    @click="deleteRun"
                >
                    Delete Draft
                </UiButton>
                <UiButton
                    v-if="can('payroll.finalize') && selectedRun.status === 'processed'"
                    variant="primary"
                    @click="finalizeRun"
                >
                    Finalize Payroll
                </UiButton>
                <UiButton
                    v-if="can('payroll.finalize') && selectedRun.status === 'finalised'"
                    variant="danger"
                    @click="showReopenModal = true"
                >
                    Reopen Payroll
                </UiButton>
            </template>
        </PageHeader>

        <UiCard
            title="Payment totals"
            :description="`${rows.length} employees | Gross ${formatMoney(selectedRun.totals.gross)} | Deductions ${formatMoney(selectedRun.totals.deductions)} | Net payable ${formatMoney(selectedRun.totals.net)}`"
        >
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="bank in bankTotals"
                    :key="bank.bank"
                    class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700"
                >
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ bank.bank }}</p>
                    <p class="mt-1 text-lg font-semibold text-brand-700 dark:text-brand-300">
                        {{ formatMoney(bank.net) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ bank.employee_count }} staff | Gross {{ formatMoney(bank.gross) }} | Ded.
                        {{ formatMoney(bank.deductions) }}
                    </p>
                </div>
            </div>
        </UiCard>

        <UiCard
            class="mt-6"
            title="Employees by bank"
            description="Payment figures grouped by employee bank for transfer preparation."
        >
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <UiInput v-model="searchText" label="Search employee" placeholder="Staff ID, NID, name, bank, or account" />
                <UiButton variant="secondary" @click="applySearch">Search</UiButton>
            </div>

            <div class="space-y-4">
                <div
                    v-for="group in bankGroups"
                    :key="group.bank"
                    class="rounded-xl border border-slate-200 dark:border-slate-700"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2 dark:border-slate-700 dark:bg-slate-800/60">
                        <p class="text-sm font-semibold">{{ group.bank }}</p>
                        <p class="text-xs text-slate-500">
                            {{ group.rows.length }} staff | Gross {{ formatMoney(group.totals.gross) }} | Ded.
                            {{ formatMoney(group.totals.deductions) }} | Net
                            <span class="font-semibold text-brand-700 dark:text-brand-300">{{ formatMoney(group.totals.net) }}</span>
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-3 py-2">Staff</th>
                                    <th class="px-3 py-2">Account</th>
                                    <th class="px-3 py-2">Attendance</th>
                                    <th class="px-3 py-2">Gross</th>
                                    <th class="px-3 py-2">Deductions</th>
                                    <th class="px-3 py-2">Net payable</th>
                                    <th class="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="row in group.rows" :key="row.employee_id">
                                    <td class="px-3 py-2">
                                        <p class="font-medium">{{ row.employee_name }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ row.staff_id }} | {{ row.department ?? 'No department' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <p>{{ row.account_name ?? '-' }}</p>
                                        <p class="text-xs text-slate-500">{{ row.account_no ?? 'No account' }}</p>
                                    </td>
                                    <td class="px-3 py-2">{{ row.days_attended }} days / {{ row.hours_worked }} hrs</td>
                                    <td class="px-3 py-2">{{ formatMoney(row.gross) }}</td>
                                    <td class="px-3 py-2">{{ formatMoney(row.deductions) }}</td>
                                    <td class="px-3 py-2 font-semibold text-brand-700 dark:text-brand-300">
                                        {{ formatMoney(row.net) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <UiButton
                                                size="sm"
                                                variant="ghost"
                                                :href="`/payroll/${selectedRun.id}/employees/${row.employee_id}/attendance`"
                                            >
                                                Attendance
                                            </UiButton>
                                            <UiButton
                                                size="sm"
                                                variant="secondary"
                                                :href="`/payroll/${selectedRun.id}/employees/${row.employee_id}/adjustments`"
                                            >
                                                Adjustments
                                            </UiButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p v-if="rows.length === 0" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    No employees match your search.
                </p>
            </div>
        </UiCard>

        <UiCard v-if="can('payroll.audit.view')" class="mt-6" title="Payroll audit log">
            <div class="space-y-2">
                <div
                    v-for="log in selectedRun.audit_logs"
                    :key="log.id"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700"
                >
                    <p class="font-medium">{{ log.event_type }} by {{ log.performed_by ?? 'Unknown' }}</p>
                    <p class="text-xs text-slate-500">{{ log.created_at }}</p>
                </div>
                <p v-if="selectedRun.audit_logs.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
                    No audit events yet.
                </p>
            </div>
        </UiCard>

        <UiModal
            :open="showReopenModal"
            title="Reopen Finalized Payroll"
            description="Enter your login password to reopen this payroll and allow edits."
            max-width="md"
            @close="showReopenModal = false"
        >
            <div class="space-y-4">
                <UiInput v-model="reopenForm.password" type="password" label="Password" />
                <UiInput v-model="reopenForm.reason" label="Reason (optional)" />
                <p v-if="page.props.errors.password" class="text-xs text-red-600 dark:text-red-400">
                    {{ page.props.errors.password }}
                </p>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="showReopenModal = false">Cancel</UiButton>
                <UiButton variant="danger" @click="reopenRun">Reopen Payroll</UiButton>
            </template>
        </UiModal>
    </AppLayout>
</template>
