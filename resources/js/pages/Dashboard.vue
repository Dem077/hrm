<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import StatCard from '@/components/ui/StatCard.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LeaveBalanceLeaveType } from '@/types/leave';

const props = defineProps<{
    hasEmployeeProfile: boolean;
    attendance: {
        period_label: string;
        present_days: number;
        absent_days: number;
        leave_days: number;
        late_minutes: number;
    } | null;
    leaveBalance: {
        leave_year_label: string;
        balances: LeaveBalanceLeaveType[];
    } | null;
}>();

const periodHint = computed(() =>
    props.attendance ? `Current payroll period: ${props.attendance.period_label}` : undefined,
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout
        title="Dashboard"
        description="Your attendance summary and leave balances."
    >
        <div
            v-if="!hasEmployeeProfile"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200"
        >
            Your login account is not linked to an employee record, so your dashboard cannot be shown.
        </div>

        <template v-else>
            <div v-if="attendance" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Days present"
                    :value="attendance.present_days"
                    :hint="periodHint"
                    accent="emerald"
                />
                <StatCard label="Days absent" :value="attendance.absent_days" :hint="periodHint" accent="slate" />
                <StatCard label="On leave" :value="attendance.leave_days" :hint="periodHint" accent="sky" />
                <StatCard
                    label="Late minutes"
                    :value="attendance.late_minutes"
                    :hint="periodHint"
                    accent="amber"
                />
            </div>

            <UiCard
                v-if="leaveBalance"
                class="mt-8"
                title="Leave balance"
                :description="`Current leave year: ${leaveBalance.leave_year_label}`"
                padding="none"
            >
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
                            <tr v-for="balance in leaveBalance.balances" :key="balance.id">
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
                            <tr v-if="leaveBalance.balances.length === 0">
                                <td colspan="4" class="px-5 py-12 text-center text-slate-500">
                                    No leave types are configured.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>
        </template>
    </AppLayout>
</template>
