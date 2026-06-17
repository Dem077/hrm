<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref, watch, computed } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiDateInput from '@/components/ui/UiDateInput.vue';
import UiSearchableSelect from '@/components/ui/UiSearchableSelect.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AddManualPunchModal from '@/pages/AttendanceSheets/components/AddManualPunchModal.vue';
import PunchEditsModal from '@/pages/AttendanceSheets/components/PunchEditsModal.vue';
import RemovePunchModal from '@/pages/AttendanceSheets/components/RemovePunchModal.vue';
import { formatDate, formatTime } from '@/lib/format';
import type { AttendanceDutyPolicy, AttendanceSheetRow, Paginated, PayrollPeriodSettings } from '@/types/attendance';

const punchModalOpen = ref(false);
const editsModalOpen = ref(false);
const editsModalRow = ref<AttendanceSheetRow | null>(null);
const removeModalOpen = ref(false);
const removeTarget = ref({
    employeeName: '',
    date: '',
    punchLabel: '',
    logId: null as number | null,
});

const props = defineProps<{
    rows: Paginated<AttendanceSheetRow>;
    currentPolicy?: AttendanceDutyPolicy;
    canViewAll: boolean;
    canAddPunch: boolean;
    canRemovePunch: boolean;
    hasEmployeeProfile: boolean;
    punchEmployees: Array<{ id: number; label: string }>;
    punchDefaults: {
        employee_id: number | null;
        duty_date: string;
    };
    payrollPeriod: PayrollPeriodSettings;
    departments: Array<{ id: number; name: string }>;
    employees: Array<{ id: number; label: string }>;
    filters: {
        payroll_period: string;
        from: string;
        to: string;
        department_id: number | null;
        employee_id: number | null;
    };
    limits: {
        max_days: number;
    };
    singleDayOnly: boolean;
}>();

const filters = reactive({
    payroll_period: props.filters.payroll_period,
    from: props.filters.from,
    to: props.filters.to,
    department_id: props.filters.department_id ?? '',
    employee_id: props.filters.employee_id ?? '',
});

const isAllEmployees = computed(() => props.canViewAll && !filters.employee_id);
const isCustomPayrollPeriod = computed(() => filters.payroll_period === 'custom' && !isAllEmployees.value);

const selectedEmployeeLabel = computed(() => {
    if (!filters.employee_id) {
        return null;
    }

    return props.employees.find((employee) => String(employee.id) === String(filters.employee_id))?.label ?? null;
});

watch(
    () => props.filters,
    (value) => {
        filters.payroll_period = value.payroll_period;
        filters.from = value.from;
        filters.to = value.to;
        filters.department_id = value.department_id ?? '';
        filters.employee_id = value.employee_id ?? '';
    },
    { deep: true },
);

const employeeOptions = computed(() =>
    props.employees.map((employee) => ({
        value: employee.id,
        label: employee.label,
    })),
);

watch(
    () => filters.department_id,
    () => {
        filters.employee_id = '';
    },
);

watch(
    () => filters.employee_id,
    (value) => {
        if (!value && props.canViewAll) {
            filters.payroll_period = 'day';
            filters.to = filters.from;
            return;
        }

        if (value && filters.payroll_period === 'day') {
            filters.payroll_period = '0';
        }
    },
);

watch(
    () => filters.payroll_period,
    (value) => {
        if (isAllEmployees.value || value === 'custom') {
            return;
        }

        const period = props.payrollPeriod.recent.find((item) => String(item.offset) === String(value));

        if (period) {
            filters.from = period.from;
            filters.to = period.to;
        }
    },
);

watch(
    () => filters.from,
    (value) => {
        if (isAllEmployees.value) {
            filters.to = value;
        }
    },
);

function markCustomPayrollPeriod() {
    if (isAllEmployees.value) {
        filters.payroll_period = 'day';
        filters.to = filters.from;
        return;
    }

    filters.payroll_period = 'custom';
}

function payrollPeriodOptionLabel(period: PayrollPeriodSettings['recent'][number]): string {
    if (period.is_current) {
        return `Current (${period.label})`;
    }

    if (period.offset === 1) {
        return `Previous (${period.label})`;
    }

    return period.label;
}

function openAddPunchModal() {
    punchModalOpen.value = true;
}

function openPunchEditsModal(row: AttendanceSheetRow) {
    editsModalRow.value = row;
    editsModalOpen.value = true;
}

function openRemovePunchModal(row: AttendanceSheetRow, punchType: 'Check in' | 'Check out', logId: number) {
    const punchedAt = punchType === 'Check in' ? row.check_in : row.check_out;

    removeTarget.value = {
        employeeName: row.employee_name,
        date: row.date,
        punchLabel: `${punchType} — ${formatTime(punchedAt)}`,
        logId,
    };
    removeModalOpen.value = true;
}

function isRowRemovable(row: AttendanceSheetRow): boolean {
    return !['holiday', 'leave'].includes(row.status);
}

function applyFilters() {
    router.get(
        '/attendance-sheet',
        {
            payroll_period: filters.payroll_period,
            from: filters.from,
            to: filters.to,
            department_id: filters.department_id || undefined,
            employee_id: filters.employee_id || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}
</script>

<template>
    <Head title="Attendance Sheet" />

    <AppLayout>
        <PageHeader
            title="Attendance sheet"
            :description="
                canViewAll
                    ? 'Processed punch logs with check-in/out, working hours, and late minutes.'
                    : 'Your attendance records for the selected date range.'
            "
        >
            <template v-if="canAddPunch && hasEmployeeProfile" #actions>
                <UiButton variant="primary" @click="openAddPunchModal">Add manual punch</UiButton>
            </template>
        </PageHeader>

        <div
            v-if="!hasEmployeeProfile"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200"
        >
            Your login account is not linked to an employee record, so attendance cannot be shown.
        </div>

        <div class="mb-6">
            <UiCard padding="sm">
                <form
                    class="grid gap-4 md:grid-cols-2"
                    :class="canViewAll ? 'xl:grid-cols-[1.2fr_1fr_1fr_1fr_1fr_auto]' : 'xl:grid-cols-[1.2fr_1fr_1fr_auto]'"
                    @submit.prevent="applyFilters"
                >
                    <UiSelect v-if="!isAllEmployees" v-model="filters.payroll_period" label="Payroll period">
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
                        :label="isAllEmployees ? 'Date' : 'From'"
                        :disabled="!isAllEmployees && !isCustomPayrollPeriod"
                        @input="markCustomPayrollPeriod"
                    />
                    <UiDateInput
                        v-if="!isAllEmployees"
                        v-model="filters.to"
                        label="To"
                        :disabled="!isCustomPayrollPeriod"
                        @input="markCustomPayrollPeriod"
                    />
                    <UiSelect v-if="canViewAll" v-model="filters.department_id" label="Department">
                        <option value="">All departments</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </UiSelect>
                    <UiSearchableSelect
                        v-if="canViewAll"
                        v-model="filters.employee_id"
                        label="Employee"
                        empty-label="All employees"
                        placeholder="Search by name or staff ID..."
                        :options="employeeOptions"
                        :selected-label="selectedEmployeeLabel"
                    />
                    <div class="flex items-end md:col-span-2" :class="canViewAll ? 'xl:col-span-1' : 'xl:col-span-1'">
                        <UiButton type="submit" variant="primary">Apply</UiButton>
                    </div>
                </form>

                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    <template v-if="isAllEmployees">
                        All employees view is limited to one day at a time. Pick a date, or select an employee for a payroll period range.
                    </template>
                    <template v-else>
                        Payroll periods start on day {{ payrollPeriod.start_day }} of each month
                        <template v-if="payrollPeriod.end_day"> and end on day {{ payrollPeriod.end_day }} of the following month</template>.
                        Absent days are included automatically. Max {{ limits.max_days }} days per load.
                    </template>
                    Duty times on each row follow the policy for that date.
                </p>
            </UiCard>
        </div>

        <UiCard padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Date</th>
                            <th class="px-5 py-3.5 font-medium">Emp No</th>
                            <th class="px-5 py-3.5 font-medium">Employee</th>
                            <th class="px-5 py-3.5 font-medium">Department</th>
                            <th class="px-5 py-3.5 font-medium">Duty start</th>
                            <th class="px-5 py-3.5 font-medium">Duty end</th>
                            <th class="px-5 py-3.5 font-medium">Check in</th>
                            <th class="px-5 py-3.5 font-medium">Check out</th>
                            <th class="px-5 py-3.5 font-medium">Hours</th>
                            <th class="px-5 py-3.5 font-medium">Late</th>
                            <th class="px-5 py-3.5 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="row in rows.data" :key="`${row.date}-${row.employee_id}`" class="hover:bg-slate-50/60 dark:hover:bg-surface-elevated/60">
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">
                                <div class="flex items-center gap-2">
                                    <span>{{ formatDate(row.date) }}</span>
                                    <button
                                        v-if="row.has_punch_edits"
                                        type="button"
                                        class="inline-flex rounded-md p-1 text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-950/40 dark:hover:text-amber-300"
                                        title="View punch edits"
                                        @click="openPunchEditsModal(row)"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-700 dark:text-slate-300">{{ row.staff_id }}</td>
                            <td class="px-5 py-4">
                                <Link
                                    v-if="canViewAll"
                                    :href="`/employees/${row.employee_id}`"
                                    class="font-medium text-brand-700 hover:text-brand-600 hover:underline dark:text-brand-400 dark:hover:text-brand-300"
                                >
                                    {{ row.employee_name }}
                                </Link>
                                <span v-else class="font-medium text-slate-900 dark:text-slate-100">{{ row.employee_name }}</span>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ row.department ?? '—' }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ row.duty_start_time }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ row.duty_end_time }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">
                                <div class="flex items-center gap-1.5">
                                    <span>{{ formatTime(row.check_in) }}</span>
                                    <button
                                        v-if="canRemovePunch && row.check_in_log_id && isRowRemovable(row)"
                                        type="button"
                                        class="inline-flex rounded-md p-1 text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                                        title="Remove check in"
                                        @click="openRemovePunchModal(row, 'Check in', row.check_in_log_id)"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">
                                <div class="flex items-center gap-1.5">
                                    <span>{{ formatTime(row.check_out) }}</span>
                                    <button
                                        v-if="canRemovePunch && row.check_out_log_id && isRowRemovable(row)"
                                        type="button"
                                        class="inline-flex rounded-md p-1 text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300"
                                        title="Remove check out"
                                        @click="openRemovePunchModal(row, 'Check out', row.check_out_log_id)"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ row.working_hours_label }}</td>
                            <td class="px-5 py-4">
                                <span v-if="row.late_minutes" class="font-medium text-amber-700 dark:text-amber-300">
                                    {{ row.late_minutes }}m
                                </span>
                                <span v-else class="text-slate-500">—</span>
                            </td>
                            <td class="px-5 py-4">
                                <UiBadge :label="row.status_label" :color="row.status_color" />
                                <p v-if="row.holiday_name" class="mt-1 text-xs text-slate-500">{{ row.holiday_name }}</p>
                            </td>
                        </tr>
                        <tr v-if="rows.data.length === 0">
                            <td colspan="11" class="px-5 py-12 text-center text-slate-500">
                                No attendance rows for this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <AddManualPunchModal
            :open="punchModalOpen"
            :employees="punchEmployees"
            :can-select-employee="canViewAll"
            :defaults="punchDefaults"
            @close="punchModalOpen = false"
        />

        <PunchEditsModal :open="editsModalOpen" :row="editsModalRow" @close="editsModalOpen = false" />

        <RemovePunchModal
            :open="removeModalOpen"
            :employee-name="removeTarget.employeeName"
            :date="removeTarget.date"
            :punch-label="removeTarget.punchLabel"
            :log-id="removeTarget.logId"
            @close="removeModalOpen = false"
        />

        <div v-if="rows.links.length > 3" class="mt-5 flex flex-wrap gap-2">
            <Link
                v-for="link in rows.links"
                :key="`${link.label}-${link.url}`"
                :href="link.url ?? '#'"
                class="rounded-lg border px-3 py-1.5 text-sm transition"
                :class="[
                    link.active
                        ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-600/40 dark:bg-brand-600/15 dark:text-brand-400'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400 dark:hover:bg-surface-muted dark:hover:text-slate-200',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
