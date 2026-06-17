<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { formatDate, formatTime } from '@/lib/format';
import type { AttendancePunchEdit, AttendanceSheetRow } from '@/types/attendance';

defineProps<{
    open: boolean;
    row: AttendanceSheetRow | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

function actionLabel(action: AttendancePunchEdit['action']): string {
    return action === 'added' ? 'Added' : 'Removed';
}

function actionColor(action: AttendancePunchEdit['action']): string {
    return action === 'added'
        ? 'bg-brand-50 text-brand-800 dark:bg-brand-600/15 dark:text-brand-300'
        : 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300';
}
</script>

<template>
    <UiModal
        :open="open"
        title="Punch edits"
        :description="row ? `Manual changes for ${row.employee_name} on ${formatDate(row.date)}.` : ''"
        @close="emit('close')"
    >
        <div v-if="row && row.punch_edits.length > 0" class="grid gap-3">
            <div
                v-for="(edit, index) in row.punch_edits"
                :key="`${edit.action}-${edit.punched_at}-${index}`"
                class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
            >
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-md px-2 py-0.5 text-xs font-medium" :class="actionColor(edit.action)">
                        {{ actionLabel(edit.action) }}
                    </span>
                    <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ edit.punch_type }}</span>
                    <span class="font-mono text-sm text-slate-500 dark:text-slate-400">{{ formatTime(edit.punched_at) }}</span>
                </div>
                <p v-if="edit.acted_by_name" class="mb-2 text-sm text-slate-500 dark:text-slate-400">
                    By {{ edit.acted_by_name }}
                </p>
                <p class="whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ edit.reason }}</p>
            </div>
        </div>

        <p v-else class="text-sm text-slate-500 dark:text-slate-400">No punch edits for this day.</p>

        <div class="mt-6 flex justify-end">
            <UiButton type="button" variant="ghost" @click="emit('close')">Close</UiButton>
        </div>
    </UiModal>
</template>
