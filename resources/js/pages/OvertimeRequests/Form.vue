<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import ApprovalRoutePreview from '@/components/approvals/ApprovalRoutePreview.vue';
import UiAlert from '@/components/ui/UiAlert.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';

type EligibleDay = {
    overtime_date: string;
    label: string;
    duty_end_time: string;
    check_out_time: string;
    start_time: string;
    end_time: string;
    worked_hours: number;
    claimed_hours: number;
    available_hours: number;
};

type ApprovalPreview = {
    first_approver: { id: number; name: string; staff_id: string } | null;
    goes_directly_to_hr: boolean;
    steps: Array<{
        key: string;
        label: string;
        status: string;
        approver: { id: number; name: string; staff_id: string } | null;
        skip_reason: string | null;
    }>;
    hint: string | null;
};

const props = defineProps<{
    approver: { id: number; name: string; staff_id: string } | null;
    approvalPreview?: ApprovalPreview | null;
    eligibleDays: EligibleDay[];
    request: {
        overtime_date: string;
        start_time: string;
        end_time: string;
        hours: string | number;
        reason: string;
    };
}>();

const form = useForm({
    overtime_date: props.request.overtime_date,
    hours: props.request.hours,
    reason: props.request.reason,
});

const selectedDay = computed(() =>
    props.eligibleDays.find((day) => day.overtime_date === form.overtime_date) ?? null,
);

watch(
    () => form.overtime_date,
    (date) => {
        const day = props.eligibleDays.find((item) => item.overtime_date === date);

        if (day) {
            form.hours = day.available_hours;
        }
    },
);

function submit() {
    form.post('/overtime-requests', { preserveScroll: true });
}
</script>

<template>
    <Head title="Apply for Overtime" />

    <AppLayout>
        <PageHeader
            title="Apply for overtime"
            description="Select extra hours you already worked after duty end. You cannot invent overtime times."
        >
            <template #actions>
                <UiButton href="/overtime-requests" variant="ghost">Back</UiButton>
            </template>
        </PageHeader>

        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
            <UiAlert
                tone="info"
                message="Only days where your check-out was after duty end are listed. Hours already requested or approved are deducted."
            />

            <UiCard title="Overtime details">
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <UiSelect v-model="form.overtime_date" label="Extra hours worked" required :error="form.errors.overtime_date">
                            <option v-for="day in eligibleDays" :key="day.overtime_date" :value="day.overtime_date">
                                {{ day.label }}
                            </option>
                        </UiSelect>
                    </div>

                    <div
                        v-if="selectedDay"
                        class="md:col-span-2 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated sm:grid-cols-3"
                    >
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Duty end</p>
                            <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ selectedDay.duty_end_time }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Check-out</p>
                            <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ selectedDay.check_out_time }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Worked after duty</p>
                            <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ selectedDay.worked_hours }} h</p>
                        </div>
                    </div>

                    <UiInput
                        v-model="form.hours"
                        label="Hours to claim"
                        type="number"
                        step="0.25"
                        min="0.25"
                        :max="selectedDay?.available_hours ?? undefined"
                        required
                        :error="form.errors.hours"
                    />
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
                        <p class="font-medium text-slate-700 dark:text-slate-300">Available to claim</p>
                        <p class="mt-1 text-slate-600 dark:text-slate-400">
                            {{ selectedDay ? `${selectedDay.available_hours} h` : '—' }}
                            <span v-if="selectedDay && selectedDay.claimed_hours > 0" class="text-xs text-slate-500">
                                ({{ selectedDay.claimed_hours }} h already claimed)
                            </span>
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <ApprovalRoutePreview :preview="approvalPreview ?? null" :fallback-approver="approver" />
                    </div>
                </div>

                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Reason</label>
                    <textarea
                        v-model="form.reason"
                        rows="4"
                        required
                        class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                        :class="form.errors.reason ? 'border-red-400' : ''"
                    />
                    <p v-if="form.errors.reason" class="mt-1.5 text-sm text-red-600">{{ form.errors.reason }}</p>
                </div>
            </UiCard>

            <div class="flex justify-end gap-2">
                <UiButton href="/overtime-requests" variant="ghost">Cancel</UiButton>
                <UiButton type="submit" variant="primary" :disabled="form.processing || !selectedDay">Submit request</UiButton>
            </div>
        </form>
    </AppLayout>
</template>
