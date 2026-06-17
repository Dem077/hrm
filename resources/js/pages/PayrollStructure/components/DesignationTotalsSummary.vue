<script setup lang="ts">
import { computed } from 'vue';

import { calculateDesignationTotals, formatPayrollMoney } from '@/lib/payroll';
import type { DesignationPayrollItem } from '@/types/payroll';

const props = defineProps<{
    items: DesignationPayrollItem[];
}>();

const totals = computed(() => calculateDesignationTotals(props.items));
</script>

<template>
    <div class="space-y-3 rounded-lg bg-slate-50 p-4 text-sm dark:bg-surface-elevated">
        <div class="grid gap-2 md:grid-cols-3">
            <div>
                <span class="text-slate-500 dark:text-slate-400">Fixed additions:</span>
                <span class="ml-2 font-medium text-slate-900 dark:text-white">{{ formatPayrollMoney(totals.additions) }}</span>
            </div>
            <div>
                <span class="text-slate-500 dark:text-slate-400">Fixed deductions:</span>
                <span class="ml-2 font-medium text-slate-900 dark:text-white">{{ formatPayrollMoney(totals.deductions) }}</span>
            </div>
            <div>
                <span class="text-slate-500 dark:text-slate-400">Fixed net:</span>
                <span class="ml-2 font-semibold text-slate-900 dark:text-white">{{ formatPayrollMoney(totals.net) }}</span>
            </div>
        </div>
        <p v-if="totals.has_attendance_allowance" class="text-xs text-slate-500 dark:text-slate-400">
            {{ totals.attendance_allowance_count }} attendance allowance component(s) are excluded from fixed net.
            They are calculated from rate × attended days / worked hours.
        </p>
        <p v-if="totals.has_loans" class="text-xs text-slate-500 dark:text-slate-400">
            {{ totals.loan_count }} loan(s) included in deductions as monthly repayments for their set period.
        </p>
    </div>
</template>
