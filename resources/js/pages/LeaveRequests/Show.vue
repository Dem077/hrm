<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime } from '@/lib/format';
import type { LeaveRequestItem } from '@/types/leave';

const props = defineProps<{
    leaveRequest: LeaveRequestItem;
    canApprove: boolean;
    canApproveHr: boolean;
    canCancel: boolean;
}>();

const reviewForm = useForm({
    review_notes: '',
});

function approve() {
    reviewForm.post(`/leave-requests/${props.leaveRequest.id}/approve`, { preserveScroll: true });
}

function reject() {
    if (!confirm('Reject this leave request?')) {
        return;
    }

    reviewForm.post(`/leave-requests/${props.leaveRequest.id}/reject`, { preserveScroll: true });
}

function cancelRequest() {
    if (!confirm('Cancel this leave request?')) {
        return;
    }

    router.post(`/leave-requests/${props.leaveRequest.id}/cancel`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="leaveRequest.record_number" />

    <AppLayout>
        <PageHeader :title="leaveRequest.record_number" :description="leaveRequest.leave_type?.name ?? 'Leave request'">
            <template #actions>
                <UiBadge :label="leaveRequest.status_label" :color="leaveRequest.status_color" />
                <UiButton href="/leave-requests" variant="ghost">Back</UiButton>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <UiCard title="Request details">
                <dl class="space-y-4 text-sm">
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Record number</dt>
                        <dd class="font-mono font-medium text-slate-900 dark:text-slate-100">{{ leaveRequest.record_number }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Employee</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">
                            {{ leaveRequest.employee?.name }} ({{ leaveRequest.employee?.staff_id }})
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Department</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ leaveRequest.employee?.department?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Leave type</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ leaveRequest.leave_type?.name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Dates</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            {{ formatDate(leaveRequest.start_date) }} – {{ formatDate(leaveRequest.end_date) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Days</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ leaveRequest.days_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Current approver</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ leaveRequest.approver_label ?? leaveRequest.approver?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Reason</dt>
                        <dd class="mt-2 whitespace-pre-wrap text-slate-800 dark:text-slate-200">{{ leaveRequest.reason }}</dd>
                    </div>
                    <div v-if="leaveRequest.document_url" class="border-t border-slate-100 pt-3 dark:border-slate-800">
                        <dt class="text-slate-500">Supporting document</dt>
                        <dd class="mt-2">
                            <a :href="leaveRequest.document_url" target="_blank" class="text-brand-600 hover:underline dark:text-brand-400">
                                View uploaded document
                            </a>
                        </dd>
                    </div>
                </dl>
            </UiCard>

            <UiCard title="Review">
                <dl class="space-y-4 text-sm">
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Submitted</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(leaveRequest.created_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Manager/HOD reviewed by</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ leaveRequest.manager_reviewed_by?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Manager/HOD reviewed at</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(leaveRequest.manager_reviewed_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Manager/HOD notes</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ leaveRequest.manager_review_notes ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">HR reviewed by</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ leaveRequest.reviewed_by?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">HR reviewed at</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(leaveRequest.reviewed_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">HR notes</dt>
                        <dd class="mt-2 whitespace-pre-wrap text-slate-800 dark:text-slate-200">{{ leaveRequest.review_notes ?? '—' }}</dd>
                    </div>
                </dl>

                <form
                    v-if="canApprove || canApproveHr"
                    class="mt-6 space-y-4 border-t border-slate-100 pt-6 dark:border-slate-800"
                    @submit.prevent="approve"
                >
                    <p v-if="canApproveHr" class="text-sm text-slate-600 dark:text-slate-400">Final HR approval — this will complete the leave request.</p>
                    <p v-else-if="canApprove" class="text-sm text-slate-600 dark:text-slate-400">First approval — the request will then go to HR for final approval.</p>
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
