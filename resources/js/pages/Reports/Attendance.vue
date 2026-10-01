<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiDateInput from '@/components/ui/UiDateInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportJobProgressModal from '@/pages/Reports/components/ReportJobProgressModal.vue';
import { useReportJobProgress } from '@/pages/Reports/components/useReportJobProgress';

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

const job = useReportJobProgress();
const rows = ref<AttendanceReportRow[]>([...props.rows]);
const loadError = ref<string | null>(null);
const hasLoaded = ref(false);

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

function queryBody() {
    return {
        payroll_period: filters.payroll_period,
        from: filters.from || undefined,
        to: filters.to || undefined,
        department_id: filters.department_id || undefined,
    };
}

async function fetchResult(jobId: string): Promise<void> {
    const response = await fetch(`/reports/attendance/jobs/${jobId}/result`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (! response.ok) {
        throw new Error('Failed to load report result.');
    }

    const payload = await response.json();
    rows.value = Array.isArray(payload.rows) ? payload.rows : [];
    hasLoaded.value = true;
}

async function loadReport(): Promise<void> {
    if (job.submitting.value) {
        return;
    }

    loadError.value = null;
    const params = new URLSearchParams();
    Object.entries(queryBody()).forEach(([key, value]) => {
        if (value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });
    window.history.replaceState({}, '', `/reports/attendance?${params.toString()}`);

    try {
        const result = await job.startJob('/reports/attendance/jobs', queryBody());
        await fetchResult(String(result.job_id));
        job.close();
    } catch (err) {
        if (err instanceof Error && err.message === 'Cancelled') {
            job.close();
            return;
        }

        loadError.value = err instanceof Error ? err.message : 'Failed to load report.';
    }
}

async function downloadCsv(): Promise<void> {
    if (job.submitting.value) {
        return;
    }

    loadError.value = null;

    try {
        const result = await job.startJob('/reports/attendance/download-jobs', queryBody());
        job.close();
        window.location.href = `/reports/jobs/${result.job_id}/download`;
    } catch (err) {
        if (err instanceof Error && err.message === 'Cancelled') {
            job.close();
            return;
        }

        loadError.value = err instanceof Error ? err.message : 'Failed to download report.';
    }
}

onMounted(() => {
    void loadReport();
});

const displayRows = computed(() =>
    rows.value.map((row) => [
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
                <UiButton variant="primary" :disabled="job.submitting.value" @click="downloadCsv">
                    {{ job.submitting.value ? 'Working…' : 'Download CSV' }}
                </UiButton>
            </template>
        </PageHeader>

        <UiCard class="mb-4">
            <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="loadReport">
                <UiSelect v-model="filters.payroll_period" label="Payroll period" :disabled="job.submitting.value">
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
                    :disabled="!isCustomPayrollPeriod || job.submitting.value"
                    @input="markCustomPayrollPeriod"
                />
                <UiDateInput
                    v-model="filters.to"
                    label="To"
                    :disabled="!isCustomPayrollPeriod || job.submitting.value"
                    @input="markCustomPayrollPeriod"
                />
                <UiSelect v-model="filters.department_id" label="Department" :disabled="job.submitting.value">
                    <option value="">All departments</option>
                    <option v-for="department in departments" :key="department.id" :value="String(department.id)">
                        {{ department.name }}
                    </option>
                </UiSelect>
                <div class="flex items-end">
                    <UiButton type="submit" variant="primary" :disabled="job.submitting.value">
                        {{ job.submitting.value ? 'Loading…' : 'Apply' }}
                    </UiButton>
                </div>
            </form>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                Normal = present / incomplete days. Late = late days. Late min = total minutes late. Holiday includes
                weekends and public holidays. Unknown leave types count under N/A.
            </p>
            <p v-if="loadError" class="mt-2 text-sm text-rose-600 dark:text-rose-400">
                {{ loadError }}
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
                        <tr v-if="hasLoaded && rows.length === 0">
                            <td :colspan="headers.length" class="px-5 py-12 text-center text-slate-500">
                                No attendance data for this period.
                            </td>
                        </tr>
                        <tr v-else-if="!hasLoaded && !job.submitting.value">
                            <td :colspan="headers.length" class="px-5 py-12 text-center text-slate-500">
                                Apply filters to load the report.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <ReportJobProgressModal
            :open="job.open.value"
            :progress="job.progress.value"
            :percent="job.percent.value"
            :message="job.message.value"
            :error="job.error.value"
            :can-cancel="job.canCancel.value"
            :cancelling="job.cancelling.value"
            :is-terminal="job.isTerminal.value"
            unit-label="chunks"
            @cancel="job.cancelJob()"
            @close="job.close()"
        />
    </AppLayout>
</template>
