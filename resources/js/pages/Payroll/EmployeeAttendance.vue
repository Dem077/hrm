<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatTime } from '@/lib/format';

defineProps<{
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
    attendance_rows: Array<{
        date: string;
        status_label: string;
        check_in: string | null;
        check_out: string | null;
        working_hours_label: string;
    }>;
}>();
</script>

<template>
    <Head :title="`Attendance - ${employee.employee_name}`" />

    <AppLayout>
        <PageHeader
            :title="employee.employee_name"
            :description="`${employee.staff_id ?? '-'} | ${run.reference_no} | ${run.period_label}`"
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

        <UiCard
            title="Employee attendance"
            :description="`${employee.days_attended} days / ${employee.hours_worked} hrs for ${formatDate(run.period_from)} to ${formatDate(run.period_to)}`"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Check In</th>
                            <th class="px-3 py-2">Check Out</th>
                            <th class="px-3 py-2">Worked</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="row in attendance_rows" :key="`${row.date}-${row.check_in}`">
                            <td class="px-3 py-2">{{ formatDate(row.date) }}</td>
                            <td class="px-3 py-2">{{ row.status_label }}</td>
                            <td class="px-3 py-2">{{ formatTime(row.check_in) }}</td>
                            <td class="px-3 py-2">{{ formatTime(row.check_out) }}</td>
                            <td class="px-3 py-2">{{ row.working_hours_label }}</td>
                        </tr>
                        <tr v-if="attendance_rows.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">
                                No attendance records for this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>
    </AppLayout>
</template>
