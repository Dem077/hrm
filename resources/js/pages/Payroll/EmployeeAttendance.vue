<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatTime } from '@/lib/format';
import type { AttendanceSheetRow } from '@/types/attendance';

const props = defineProps<{
    run: {
        id: number;
        reference_no: string;
        period_label: string;
        period_from: string;
        period_to: string;
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
    };
    attendance_rows: AttendanceSheetRow[];
}>();

const summary = computed(() => {
    const rows = props.attendance_rows;
    const lateDays = rows.filter((row) => (row.late_minutes ?? 0) > 0).length;
    const lateMinutes = rows.reduce((sum, row) => sum + (row.late_minutes ?? 0), 0);
    const absentDays = rows.filter((row) => row.status === 'absent').length;
    const leaveDays = rows.filter((row) => row.status === 'leave' || Boolean(row.leave_type_name)).length;
    const incompleteDays = rows.filter((row) => row.status === 'incomplete').length;

    return {
        lateDays,
        lateMinutes,
        absentDays,
        leaveDays,
        incompleteDays,
        totalDays: rows.length,
    };
});

function weekdayLabel(date: string): string {
    const parsed = new Date(`${date}T12:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    return parsed.toLocaleDateString(undefined, { weekday: 'short' });
}

function rowToneClass(row: AttendanceSheetRow): string {
    if (row.status === 'absent') {
        return 'bg-red-50/40 dark:bg-red-950/20';
    }

    if (row.status === 'late' || (row.late_minutes ?? 0) > 0) {
        return 'bg-amber-50/35 dark:bg-amber-950/15';
    }

    if (row.status === 'leave' || row.leave_type_name) {
        return 'bg-sky-50/40 dark:bg-sky-950/20';
    }

    if (row.status === 'incomplete') {
        return 'bg-orange-50/35 dark:bg-orange-950/15';
    }

    if (row.status === 'holiday' || row.holiday_name) {
        return 'bg-violet-50/35 dark:bg-violet-950/15';
    }

    return '';
}

function statusAccentClass(row: AttendanceSheetRow): string {
    if (row.status === 'absent') {
        return 'border-l-red-400 dark:border-l-red-500';
    }

    if (row.status === 'late' || (row.late_minutes ?? 0) > 0) {
        return 'border-l-amber-400 dark:border-l-amber-500';
    }

    if (row.status === 'present') {
        return 'border-l-emerald-400 dark:border-l-emerald-500';
    }

    if (row.status === 'leave' || row.leave_type_name) {
        return 'border-l-sky-400 dark:border-l-sky-500';
    }

    if (row.status === 'incomplete') {
        return 'border-l-orange-400 dark:border-l-orange-500';
    }

    return 'border-l-transparent';
}
</script>

<template>
    <Head :title="`Attendance - ${employee.employee_name}`" />

    <AppLayout>
        <PageHeader
            :title="employee.employee_name"
            :description="`${employee.staff_id ?? '—'} · ${run.reference_no} · ${run.period_label}`"
        >
            <template #actions>
                <UiButton :href="`/payroll/${run.id}`" variant="ghost">Back to payroll</UiButton>
                <UiButton
                    :href="`/payroll/${run.id}/employees/${employee.employee_id}/adjustments`"
                    variant="secondary"
                >
                    Adjustments
                </UiButton>
            </template>
        </PageHeader>

        <div class="mb-6 flex flex-wrap gap-2 text-sm">
            <span
                v-if="employee.department"
                class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-slate-700 ring-1 ring-inset ring-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-700"
            >
                {{ employee.department }}
            </span>
            <span
                v-if="employee.designation"
                class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-slate-700 ring-1 ring-inset ring-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-700"
            >
                {{ employee.designation }}
            </span>
            <span
                class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-brand-800 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/50 dark:text-brand-300 dark:ring-brand-800/60"
            >
                {{ formatDate(run.period_from) }} – {{ formatDate(run.period_to) }}
            </span>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-surface-elevated px-4 py-4 dark:border-slate-800">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Days attended</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">
                    {{ employee.days_attended }}
                    <span class="text-sm font-normal text-slate-500">/ {{ summary.totalDays }}</span>
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-surface-elevated px-4 py-4 dark:border-slate-800">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Hours worked</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">
                    {{ employee.hours_worked }}
                    <span class="text-sm font-normal text-slate-500">hrs</span>
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-surface-elevated px-4 py-4 dark:border-slate-800">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Late</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">
                    {{ summary.lateDays }}
                    <span class="text-sm font-normal text-slate-500">day(s) · {{ summary.lateMinutes }}m</span>
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-surface-elevated px-4 py-4 dark:border-slate-800">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Absent / leave</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">
                    {{ summary.absentDays }}
                    <span class="text-sm font-normal text-slate-500">absent</span>
                    <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>
                    {{ summary.leaveDays }}
                    <span class="text-sm font-normal text-slate-500">leave</span>
                </p>
            </div>
        </div>

        <UiCard padding="none">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Daily attendance</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Punch times against duty hours for this payroll period.
                        <span v-if="summary.incompleteDays" class="text-orange-700 dark:text-orange-300">
                            {{ summary.incompleteDays }} incomplete day(s).
                        </span>
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Date</th>
                            <th class="px-5 py-3.5 font-medium">Duty</th>
                            <th class="px-5 py-3.5 font-medium">Check in</th>
                            <th class="px-5 py-3.5 font-medium">Check out</th>
                            <th class="px-5 py-3.5 font-medium">Worked</th>
                            <th class="px-5 py-3.5 font-medium">Late</th>
                            <th class="px-5 py-3.5 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="row in attendance_rows"
                            :key="`${row.date}-${row.check_in ?? 'none'}`"
                            class="border-l-4 transition hover:bg-slate-50/70 dark:hover:bg-surface-elevated/50"
                            :class="[statusAccentClass(row), rowToneClass(row)]"
                        >
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-slate-900 dark:text-white">{{ formatDate(row.date) }}</p>
                                <p class="text-xs text-slate-500">{{ weekdayLabel(row.date) }}</p>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-slate-600 dark:text-slate-400">
                                <template v-if="row.duty_start_time || row.duty_end_time">
                                    {{ row.duty_start_time || '—' }} – {{ row.duty_end_time || '—' }}
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-slate-700 dark:text-slate-300">
                                {{ formatTime(row.check_in) }}
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-slate-700 dark:text-slate-300">
                                {{ formatTime(row.check_out) }}
                            </td>
                            <td class="px-5 py-3.5 tabular-nums text-slate-800 dark:text-slate-200">
                                {{ row.working_hours_label || '—' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    v-if="row.late_minutes"
                                    class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:ring-amber-800/60"
                                >
                                    {{ row.late_minutes }}m
                                </span>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-col gap-1">
                                    <UiBadge :label="row.status_label" :color="row.status_color" />
                                    <span
                                        v-if="row.holiday_name"
                                        class="text-xs text-violet-700 dark:text-violet-300"
                                    >
                                        {{ row.holiday_name }}
                                    </span>
                                    <span
                                        v-else-if="row.leave_type_name"
                                        class="text-xs text-sky-700 dark:text-sky-300"
                                    >
                                        {{ row.leave_type_name }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="attendance_rows.length === 0">
                            <td colspan="7" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">
                                No attendance records for this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>
    </AppLayout>
</template>
