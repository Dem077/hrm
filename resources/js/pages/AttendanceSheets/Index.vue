<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime } from '@/lib/format';
import type { AttendanceDutyPolicy, AttendanceSheetRow, Paginated } from '@/types/attendance';

const props = defineProps<{
    rows: Paginated<AttendanceSheetRow>;
    currentPolicy: AttendanceDutyPolicy;
    departments: Array<{ id: number; name: string }>;
    employees: Array<{ id: number; label: string }>;
    filters: {
        from: string;
        to: string;
        department_id: number | null;
        employee_id: number | null;
        include_absent: boolean;
    };
    limits: {
        max_days: number;
        max_days_with_absents: number;
        max_days_without_absents: number;
    };
}>();

const filters = reactive({
    from: props.filters.from,
    to: props.filters.to,
    department_id: props.filters.department_id ?? '',
    employee_id: props.filters.employee_id ?? '',
    include_absent: props.filters.include_absent,
});

watch(
    () => filters.department_id,
    () => {
        filters.employee_id = '';
    },
);

function applyFilters() {
    router.get('/attendance-sheet', filters, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Attendance Sheet" />

    <AppLayout>
        <PageHeader
            title="Attendance sheet"
            description="Processed punch logs with check-in/out, working hours, and late minutes."
        />

        <div class="mb-6">
            <UiCard padding="sm">
                <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto]" @submit.prevent="applyFilters">
                    <UiInput v-model="filters.from" label="From" type="date" />
                    <UiInput v-model="filters.to" label="To" type="date" />
                    <UiSelect v-model="filters.department_id" label="Department">
                        <option value="">All departments</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </UiSelect>
                    <UiSelect v-model="filters.employee_id" label="Employee">
                        <option value="">All employees</option>
                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                            {{ employee.label }}
                        </option>
                    </UiSelect>
                    <div class="flex items-end md:col-span-2 xl:col-span-1">
                        <UiButton type="submit" variant="primary">Apply</UiButton>
                    </div>
                </form>

                <label class="mt-4 flex items-center gap-3 text-sm text-slate-600 dark:text-slate-400">
                    <input
                        v-model="filters.include_absent"
                        type="checkbox"
                        class="rounded border-slate-300 bg-white text-brand-600 dark:border-slate-600 dark:bg-surface dark:text-brand-500"
                        @change="applyFilters"
                    />
                    Include absent employees (max {{ limits.max_days_with_absents }} days)
                </label>

                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    Duty times on each row follow the policy for that date.
                    Max {{ limits.max_days }} days per load{{ filters.include_absent ? '' : ' when absents are hidden' }}.
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
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ formatDate(row.date) }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-700 dark:text-slate-300">{{ row.staff_id }}</td>
                            <td class="px-5 py-4">
                                <Link
                                    :href="`/employees/${row.employee_id}`"
                                    class="font-medium text-brand-700 hover:text-brand-600 hover:underline dark:text-brand-400 dark:hover:text-brand-300"
                                >
                                    {{ row.employee_name }}
                                </Link>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ row.department ?? '—' }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ row.duty_start_time }}</td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ row.duty_end_time }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(row.check_in) }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(row.check_out) }}</td>
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
                            <td colspan="11" class="px-5 py-12 text-center text-slate-500">No attendance rows for this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

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
