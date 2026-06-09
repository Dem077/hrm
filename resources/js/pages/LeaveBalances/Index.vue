<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiSearchableSelect from '@/components/ui/UiSearchableSelect.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { LeaveBalanceEmployee, LeaveYearOption } from '@/types/leave';

const props = defineProps<{
    employee: LeaveBalanceEmployee | null;
    leaveYears: LeaveYearOption[];
    selectedLeaveYear: LeaveYearOption | null;
    departments: Array<{ id: number; name: string }>;
    employees: Array<{ id: number; label: string }>;
    filterLeaveTypes: Array<{ id: number; name: string }>;
    filters: {
        department_id: number | null;
        employee_id: number | null;
        leave_type_id: number | null;
        leave_year_offset: number;
    };
}>();

const filters = reactive({
    department_id: props.filters.department_id ?? '',
    employee_id: props.filters.employee_id ?? '',
    leave_type_id: props.filters.leave_type_id ?? '',
    leave_year_offset: props.filters.leave_year_offset ?? 0,
});

watch(
    () => props.filters,
    (value) => {
        filters.department_id = value.department_id ?? '';
        filters.employee_id = value.employee_id ?? '';
        filters.leave_type_id = value.leave_type_id ?? '';
        filters.leave_year_offset = value.leave_year_offset ?? 0;
    },
    { deep: true },
);

watch(
    () => filters.department_id,
    () => {
        filters.employee_id = '';
        filters.leave_year_offset = 0;
    },
);

watch(
    () => filters.employee_id,
    () => {
        filters.leave_year_offset = 0;
    },
);

const employeeOptions = computed(() =>
    props.employees.map((employee) => ({
        value: employee.id,
        label: employee.label,
    })),
);

const leaveYearOffset = computed(() => Number(filters.leave_year_offset));

const canGoToNewerLeaveYear = computed(() => leaveYearOffset.value > 0);
const canGoToOlderLeaveYear = computed(() =>
    props.leaveYears.some((year) => year.offset === leaveYearOffset.value + 1),
);

const selectedLeaveYearLabel = computed(() => {
    const match = props.leaveYears.find((year) => year.offset === leaveYearOffset.value);

    return match?.label ?? props.selectedLeaveYear?.label ?? null;
});

function applyFilters() {
    if (!filters.employee_id) {
        return;
    }

    router.get(
        '/leave-balances',
        {
            department_id: filters.department_id || undefined,
            employee_id: filters.employee_id || undefined,
            leave_type_id: filters.leave_type_id || undefined,
            leave_year_offset: leaveYearOffset.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

const exportAllUrl = computed(() => {
    const params = new URLSearchParams();
    const departmentId = filters.department_id || props.filters.department_id;

    if (departmentId) {
        params.set('department_id', String(departmentId));
    }

    const query = params.toString();

    return query ? `/leave-balances/export-all?${query}` : '/leave-balances/export-all';
});

const exportUrl = computed(() => {
    const employeeId = filters.employee_id || props.filters.employee_id || props.employee?.id;

    if (!employeeId) {
        return null;
    }

    const params = new URLSearchParams();

    const departmentId = filters.department_id || props.filters.department_id;

    if (departmentId) {
        params.set('department_id', String(departmentId));
    }

    params.set('employee_id', String(employeeId));

    const leaveTypeId = filters.leave_type_id || props.filters.leave_type_id;

    if (leaveTypeId) {
        params.set('leave_type_id', String(leaveTypeId));
    }

    params.set('leave_year_offset', String(leaveYearOffset.value));

    return `/leave-balances/export?${params.toString()}`;
});

function shiftLeaveYear(direction: 'older' | 'newer') {
    if (direction === 'older' && canGoToOlderLeaveYear.value) {
        filters.leave_year_offset = leaveYearOffset.value + 1;
    }

    if (direction === 'newer' && canGoToNewerLeaveYear.value) {
        filters.leave_year_offset = leaveYearOffset.value - 1;
    }

    applyFilters();
}
</script>

<template>
    <Head title="Leave Balances" />

    <AppLayout>
        <PageHeader
            title="Leave balances"
            description="View one employee at a time and browse their leave years from joining date."
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <UiButton :href="exportAllUrl" external variant="primary">
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M12 10v6m0 0l-3-3m3 3l3-3M6 20h12a2 2 0 002-2V8l-6-6H6a2 2 0 00-2 2v16a2 2 0 002 2z"
                            />
                        </svg>
                        Export all employees
                    </UiButton>
                    <UiButton v-if="employee && exportUrl" :href="exportUrl" external variant="secondary">
                        Export employee
                    </UiButton>
                </div>
            </template>
        </PageHeader>

        <UiCard class="mb-6" title="Filters">
            <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
                <UiSelect v-model="filters.department_id" label="Department">
                    <option value="">All departments</option>
                    <option v-for="department in departments" :key="department.id" :value="department.id">
                        {{ department.name }}
                    </option>
                </UiSelect>

                <UiSearchableSelect
                    v-model="filters.employee_id"
                    label="Employee"
                    placeholder="Search by name or staff ID..."
                    :options="employeeOptions"
                />

                <UiSelect v-model="filters.leave_type_id" label="Leave type">
                    <option value="">All leave types</option>
                    <option v-for="leaveType in filterLeaveTypes" :key="leaveType.id" :value="leaveType.id">
                        {{ leaveType.name }}
                    </option>
                </UiSelect>

                <div class="flex items-end">
                    <UiButton type="submit" variant="primary" :disabled="!filters.employee_id">View balances</UiButton>
                </div>
            </form>
        </UiCard>

        <UiCard v-if="!employee" class="text-sm text-slate-500">
            Select an employee to view their leave balances.
        </UiCard>

        <template v-else>
            <UiCard class="mb-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ employee.name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ employee.staff_id }}</p>
                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-slate-500">Department</dt>
                                <dd class="font-medium text-slate-800 dark:text-slate-200">{{ employee.department ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Joined</dt>
                                <dd class="font-medium text-slate-800 dark:text-slate-200">{{ formatDate(employee.joined_date) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="flex flex-col gap-3 sm:min-w-[320px]">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Leave year</label>
                        <div class="flex items-center gap-2">
                            <UiButton
                                type="button"
                                variant="ghost"
                                size="sm"
                                :disabled="!canGoToOlderLeaveYear"
                                @click="shiftLeaveYear('older')"
                            >
                                Older
                            </UiButton>
                            <UiSelect v-model="filters.leave_year_offset" class="flex-1" @update:model-value="applyFilters">
                                <option v-for="year in leaveYears" :key="year.offset" :value="year.offset">
                                    {{ year.label }}{{ year.is_current ? ' (Current)' : '' }}
                                </option>
                            </UiSelect>
                            <UiButton
                                type="button"
                                variant="ghost"
                                size="sm"
                                :disabled="!canGoToNewerLeaveYear"
                                @click="shiftLeaveYear('newer')"
                            >
                                Newer
                            </UiButton>
                        </div>
                        <p v-if="selectedLeaveYearLabel" class="text-xs text-slate-500">
                            Showing balances for {{ selectedLeaveYearLabel }}
                        </p>
                    </div>
                </div>
            </UiCard>

            <UiCard padding="none">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Leave type</th>
                                <th class="px-5 py-3.5 font-medium">Annual limit</th>
                                <th class="px-5 py-3.5 font-medium">Used</th>
                                <th class="px-5 py-3.5 font-medium">Remaining</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="balance in employee.balances" :key="balance.id">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ balance.name }}</div>
                                    <div v-if="balance.code" class="text-xs text-slate-500">{{ balance.code }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ balance.annual_limit ?? 'Unlimited' }}
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ balance.used_days ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ balance.remaining_days ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="employee.balances.length === 0">
                                <td colspan="4" class="px-5 py-12 text-center text-slate-500">
                                    No leave types match this filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>
        </template>
    </AppLayout>
</template>
