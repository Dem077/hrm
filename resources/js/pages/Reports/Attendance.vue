<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiDateInput from '@/components/ui/UiDateInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';

type AttendanceReportRow = {
    emp_no: string;
    name: string;
    nid: string | null;
    department: string | null;
    late_min: number;
    normal: number;
    annual_leave: number;
    family_leave: number;
    holiday: number;
    sick_leave: number;
    absent: number;
    late: number;
    duty_travel: number;
    release: number;
    umra_leave: number;
    maternity_leave: number;
    n_a: number;
};

type PayrollPeriodMeta = {
    start_day: number;
    end_day?: number | null;
    current: { from: string; to: string; label?: string };
    previous: { from: string; to: string; label?: string };
    recent: Array<{ offset: number; from: string; to: string; label?: string }>;
};

const props = defineProps<{
    rows: AttendanceReportRow[];
    headers: string[];
    filters: {
        from: string;
        to: string;
        department_id: number | null;
        payroll_period: string;
    };
    departments: Array<{ id: number; name: string }>;
    payrollPeriod: PayrollPeriodMeta;
}>();

const filters = reactive({
    payroll_period: props.filters.payroll_period || '0',
    from: props.filters.from,
    to: props.filters.to,
    department_id: props.filters.department_id ? String(props.filters.department_id) : '',
});

watch(
    () => props.filters,
    (value) => {
        filters.payroll_period = value.payroll_period || '0';
        filters.from = value.from;
        filters.to = value.to;
        filters.department_id = value.department_id ? String(value.department_id) : '';
    },
    { deep: true },
);

const isCustomPayrollPeriod = computed(() => filters.payroll_period === 'custom');

function payrollPeriodOptionLabel(period: { from: string; to: string; label?: string }) {
    return period.label || `${period.from} → ${period.to}`;
}

function markCustomPayrollPeriod() {
    filters.payroll_period = 'custom';
}

function queryParams() {
    return {
        payroll_period: filters.payroll_period,
        from: filters.from || undefined,
        to: filters.to || undefined,
        department_id: filters.department_id || undefined,
    };
}

function applyFilters() {
    router.get('/reports/attendance', queryParams(), {
        preserveState: true,
        replace: true,
    });
}

function downloadHref() {
    const params = new URLSearchParams();
    const query = queryParams();

    Object.entries(query).forEach(([key, value]) => {
        if (value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });

    const qs = params.toString();

    return qs ? `/reports/attendance/download?${qs}` : '/reports/attendance/download';
}

const displayRows = computed(() =>
    props.rows.map((row) => [
        row.emp_no,
        row.name,
        row.nid ?? '—',
        row.department ?? '—',
        row.late_min,
        row.normal,
        row.annual_leave,
        row.family_leave,
        row.holiday,
        row.sick_leave,
        row.absent,
        row.late,
        row.duty_travel,
        row.release,
        row.umra_leave,
        row.maternity_leave,
        row.n_a,
    ]),
);
</script>

<template>
    <Head title="Attendance Report" />

    <AppLayout>
        <PageHeader
            title="Attendance Report"
            description="Per-employee day counts and late minutes for the selected period. Leave types not listed roll into N/A."
        >
            <template #actions>
                <a :href="downloadHref()">
                    <UiButton variant="primary">Download CSV</UiButton>
                </a>
            </template>
        </PageHeader>

        <UiCard class="mb-4">
            <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
                <UiSelect v-model="filters.payroll_period" label="Payroll period">
                    <option
                        v-for="period in payrollPeriod.recent"
                        :key="period.offset"
                        :value="String(period.offset)"
                    >
                        {{ payrollPeriodOptionLabel(period) }}
                    </option>
                    <option value="custom">Custom range</option>
                </UiSelect>
                <UiDateInput
                    v-model="filters.from"
                    label="From"
                    :disabled="!isCustomPayrollPeriod"
                    @input="markCustomPayrollPeriod"
                />
                <UiDateInput
                    v-model="filters.to"
                    label="To"
                    :disabled="!isCustomPayrollPeriod"
                    @input="markCustomPayrollPeriod"
                />
                <UiSelect v-model="filters.department_id" label="Department">
                    <option value="">All departments</option>
                    <option v-for="department in departments" :key="department.id" :value="String(department.id)">
                        {{ department.name }}
                    </option>
                </UiSelect>
                <div class="flex items-end">
                    <UiButton type="submit" variant="primary">Apply</UiButton>
                </div>
            </form>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                Normal = present / incomplete days. Late = late days. Late min = total minutes late. Holiday includes
                weekends and public holidays. Unknown leave types count under N/A.
            </p>
        </UiCard>

        <UiCard padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead
                        class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400"
                    >
                        <tr>
                            <th
                                v-for="header in headers"
                                :key="header"
                                class="whitespace-nowrap px-4 py-3.5 font-medium"
                            >
                                {{ header }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="(row, index) in displayRows" :key="`${row[0]}-${index}`">
                            <td
                                v-for="(cell, cellIndex) in row"
                                :key="`${index}-${cellIndex}`"
                                class="whitespace-nowrap px-4 py-3 text-slate-700 dark:text-slate-300"
                                :class="cellIndex <= 3 ? 'font-medium text-slate-900 dark:text-white' : ''"
                            >
                                {{ cell }}
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td :colspan="headers.length" class="px-5 py-12 text-center text-slate-500">
                                No attendance data for this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>
    </AppLayout>
</template>
