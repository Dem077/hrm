<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import LeavePunchConflictDialog from '@/components/leave/LeavePunchConflictDialog.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiAlert from '@/components/ui/UiAlert.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLeavePunchConfirmation } from '@/composables/useLeavePunchConfirmation';
import type { LeaveTypeOption } from '@/types/leave';

const props = defineProps<{
    leaveTypes: LeaveTypeOption[];
    approver: { id: number; name: string; staff_id: string } | null;
    request: {
        leave_type_id: string | number;
        start_date: string;
        end_date: string;
        reason: string;
    };
}>();

const form = useForm({
    leave_type_id: props.request.leave_type_id,
    start_date: props.request.start_date,
    end_date: props.request.end_date,
    reason: props.request.reason,
    document: null as File | null,
    acknowledge_punch_overlap: false,
});

const selectedLeaveType = computed(() =>
    props.leaveTypes.find((type) => String(type.id) === String(form.leave_type_id)),
);

const { dialogOpen, dialogMessage, checkError, checking, requestConfirmation, closeDialog } = useLeavePunchConfirmation();

function onDocumentChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.document = target.files?.[0] ?? null;
}

function submitLeave() {
    form.post('/leave-requests', {
        forceFormData: true,
        preserveScroll: true,
    });
}

async function submit() {
    if (!form.acknowledge_punch_overlap) {
        const result = await requestConfirmation({
            start_date: String(form.start_date),
            end_date: String(form.end_date),
        });

        if (result !== 'proceed') {
            return;
        }
    }

    submitLeave();
}

function confirmPunchOverlap() {
    closeDialog();
    form.acknowledge_punch_overlap = true;
    submitLeave();
}
</script>

<template>
    <Head title="Apply for Leave" />

    <AppLayout>
        <PageHeader title="Apply for leave" description="Your request goes to your direct manager or department head first, then to HR for final approval.">
            <template #actions>
                <UiButton href="/leave-requests" variant="ghost">Back</UiButton>
            </template>
        </PageHeader>

        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
            <UiAlert v-if="checkError" tone="error" :message="checkError" />

            <UiCard title="Leave details">
                <div class="grid gap-5 md:grid-cols-2">
                    <UiSelect v-model="form.leave_type_id" label="Leave type" required :error="form.errors.leave_type_id">
                        <option value="">Select leave type</option>
                        <option v-for="leaveType in leaveTypes" :key="leaveType.id" :value="leaveType.id">
                            {{ leaveType.name }}
                        </option>
                    </UiSelect>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
                        <p class="font-medium text-slate-700 dark:text-slate-300">Approver</p>
                        <p class="mt-1 text-slate-600 dark:text-slate-400">
                            {{ approver ? `${approver.name} (${approver.staff_id})` : 'No approver assigned' }}
                        </p>
                    </div>
                    <UiInput v-model="form.start_date" label="Start date" type="date" required :error="form.errors.start_date" />
                    <UiInput v-model="form.end_date" label="End date" type="date" required :error="form.errors.end_date" />
                </div>

                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Reason</label>
                    <textarea
                        v-model="form.reason"
                        rows="4"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                    />
                    <p v-if="form.errors.reason" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.reason }}</p>
                </div>

                <div v-if="selectedLeaveType?.requires_document" class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Supporting document</label>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-700 dark:text-slate-400"
                        @change="onDocumentChange"
                    />
                    <p v-if="form.errors.document" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.document }}</p>
                </div>

                <p v-if="form.errors.acknowledge_punch_overlap" class="mt-5 text-sm text-amber-700 dark:text-amber-300">
                    {{ form.errors.acknowledge_punch_overlap }}
                </p>
            </UiCard>

            <div class="flex justify-end">
                <UiButton type="submit" variant="primary" :disabled="form.processing || checking || !approver">
                    Submit request
                </UiButton>
            </div>
        </form>

        <LeavePunchConflictDialog
            :open="dialogOpen"
            :message="dialogMessage"
            :processing="form.processing"
            @confirm="confirmPunchOverlap"
            @cancel="closeDialog"
        />
    </AppLayout>
</template>
