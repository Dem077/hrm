<script setup lang="ts">
import { computed } from 'vue';

import { formatDate } from '@/lib/format';
import type { LeaveTypeOption } from '@/types/leave';

const props = defineProps<{
    leaveType: LeaveTypeOption | null | undefined;
}>();

const hasLimit = computed(() => props.leaveType?.annual_limit !== null && props.leaveType?.annual_limit !== undefined);

const periodLabel = computed(() => {
    if (!props.leaveType?.period_start || !props.leaveType?.period_end) {
        return null;
    }

    return `${formatDate(props.leaveType.period_start)} to ${formatDate(props.leaveType.period_end)}`;
});
</script>

<template>
    <div
        v-if="hasLimit && leaveType"
        class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated"
    >
        <p class="font-medium text-slate-700 dark:text-slate-300">Annual leave balance</p>
        <p class="mt-1 text-slate-600 dark:text-slate-400">
            {{ leaveType.used_days ?? 0 }} of {{ leaveType.annual_limit }} days used
            <span v-if="leaveType.remaining_days !== null">({{ leaveType.remaining_days }} remaining)</span>
        </p>
        <p v-if="periodLabel" class="mt-1 text-xs text-slate-500 dark:text-slate-500">Leave year: {{ periodLabel }}</p>
    </div>
</template>
