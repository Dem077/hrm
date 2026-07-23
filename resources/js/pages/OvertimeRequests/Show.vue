<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime } from '@/lib/format';
import type { OvertimeRequestItem } from '@/types/overtime';

const props = defineProps<{
    overtimeRequest: OvertimeRequestItem;
    canApprove: boolean;
    canApproveHr: boolean;
    canCancel: boolean;
}>();

const reviewForm = useForm({
    review_notes: '',
});

function approve() {
    reviewForm.post(`/overtime-requests/${props.overtimeRequest.id}/approve`, { preserveScroll: true });
}

function reject() {
    if (!confirm('Reject this overtime request?')) {
        return;
    }

    reviewForm.post(`/overtime-requests/${props.overtimeRequest.id}/reject`, { preserveScroll: true });
}

function cancelRequest() {
    if (!confirm('Cancel this overtime request?')) {
        return;
    }

    router.post(`/overtime-requests/${props.overtimeRequest.id}/cancel`, {}, { preserveScroll: true });
}

function timeRange(request: OvertimeRequestItem): string {
    if (request.start_time && request.end_time) {
        return `${request.start_time} – ${request.end_time}`;
    }

    return '—';
}
</script>

<template>
    <Head :title="overtimeRequest.record_number" />

    <AppLayout>
        <PageHeader :title="overtimeRequest.record_number" description="Overtime request">
            <template #actions>
                <UiBadge :label="overtimeRequest.status_label" :color="overtimeRequest.status_color" />
                <UiButton href="/overtime-requests" variant="ghost">Back</UiButton>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <UiCard title="Request details">
                <dl class="space-y-4 text-sm">
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Record number</dt>
                        <dd class="font-mono font-medium text-slate-900 dark:text-slate-100">{{ overtimeRequest.record_number }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Employee</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">
                            {{ overtimeRequest.employee?.name }} ({{ overtimeRequest.employee?.staff_id }})
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Department</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ overtimeRequest.employee?.department?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Date</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDate(overtimeRequest.overtime_date) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Time</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ timeRange(overtimeRequest) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Hours</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ overtimeRequest.hours }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Current approver</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ overtimeRequest.approver_label ?? overtimeRequest.approver?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Reason</dt>
                        <dd class="mt-2 whitespace-pre-wrap text-slate-800 dark:text-slate-200">{{ overtimeRequest.reason }}</dd>
                    </div>
                </dl>
            </UiCard>

            <UiCard title="Approval workflow">
                <ol v-if="overtimeRequest.approval_steps?.length" class="space-y-3">
                    <li
                        v-for="step in overtimeRequest.approval_steps"
                        :key="step.id"
                        class="rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ step.step_order }}. {{ step.label }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    <template v-if="step.acted_by">
                                        Acted by {{ step.acted_by.name }}
                                        <span v-if="step.acted_at"> · {{ formatDateTime(step.acted_at) }}</span>
                                    </template>
                                    <template v-else-if="step.approver">
                                        Approver: {{ step.approver.name }} ({{ step.approver.staff_id }})
                                    </template>
                                    <template v-else-if="step.step_key === 'hr'">
                                        Human Resources
                                    </template>
                                    <template v-else>
                                        No approver assigned for this step
                                    </template>
                                </p>
                                <p v-if="step.notes" class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                    {{ step.notes }}
                                </p>
                            </div>
                            <UiBadge :label="step.status_label" :color="step.status_color" />
                        </div>
                    </li>
                </ol>
                <p v-else class="text-sm text-slate-500">
                    No workflow timeline saved for this request.
                </p>
            </UiCard>

            <UiCard title="Review">
                <dl class="space-y-4 text-sm">
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Submitted</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(overtimeRequest.created_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Latest structure review</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ overtimeRequest.manager_reviewed_by?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">HR reviewed by</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ overtimeRequest.reviewed_by?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">HR notes</dt>
                        <dd class="mt-2 whitespace-pre-wrap text-slate-800 dark:text-slate-200">{{ overtimeRequest.review_notes ?? '—' }}</dd>
                    </div>
                </dl>

                <form
                    v-if="canApprove || canApproveHr"
                    class="mt-6 space-y-4 border-t border-slate-100 pt-6 dark:border-slate-800"
                    @submit.prevent="approve"
                >
                    <p v-if="canApproveHr" class="text-sm text-slate-600 dark:text-slate-400">
                        Final HR approval — this will complete the overtime request and include hours in payroll.
                    </p>
                    <p v-else-if="canApprove" class="text-sm text-slate-600 dark:text-slate-400">
                        Structure approval — after this step, the request continues to the next configured approver or HR.
                    </p>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Notes (optional)</label>
                    <textarea
                        v-model="reviewForm.review_notes"
                        rows="3"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                    />
                    <div class="flex gap-2">
                        <UiButton type="submit" variant="primary" :disabled="reviewForm.processing">
                            {{ canApproveHr ? 'Approve (HR)' : 'Approve' }}
                        </UiButton>
                        <UiButton type="button" variant="danger" :disabled="reviewForm.processing" @click="reject">Reject</UiButton>
                    </div>
                </form>

                <div v-if="canCancel" class="mt-6 border-t border-slate-100 pt-6 dark:border-slate-800">
                    <UiButton variant="ghost" @click="cancelRequest">Cancel request</UiButton>
                </div>
            </UiCard>
        </div>
    </AppLayout>
</template>
