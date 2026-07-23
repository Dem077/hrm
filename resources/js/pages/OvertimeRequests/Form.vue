<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    approver: { id: number; name: string; staff_id: string } | null;
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
    start_time: props.request.start_time,
    end_time: props.request.end_time,
    hours: props.request.hours,
    reason: props.request.reason,
});

function hoursBetween(start: string, end: string): number | null {
    if (!start || !end) {
        return null;
    }

    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);

    if ([sh, sm, eh, em].some((n) => Number.isNaN(n))) {
        return null;
    }

    const startMinutes = sh * 60 + sm;
    const endMinutes = eh * 60 + em;

    if (endMinutes <= startMinutes) {
        return null;
    }

    return Math.round(((endMinutes - startMinutes) / 60) * 100) / 100;
}

watch(
    () => [form.start_time, form.end_time] as const,
    ([start, end]) => {
        const computed = hoursBetween(String(start), String(end));

        if (computed !== null) {
            form.hours = computed;
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
            description="Request pay for extra hours worked after your regular duty. Approvals follow the same workflow as leave."
        >
            <template #actions>
                <UiButton href="/overtime-requests" variant="ghost">Back</UiButton>
            </template>
        </PageHeader>

        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
            <UiCard title="Overtime details">
                <div class="grid gap-5 md:grid-cols-2">
                    <UiInput
                        v-model="form.overtime_date"
                        label="Date worked"
                        type="date"
                        required
                        :error="form.errors.overtime_date"
                    />
                    <UiInput v-model="form.hours" label="Hours" type="number" step="0.25" min="0.25" max="24" required :error="form.errors.hours" />
                    <UiInput v-model="form.start_time" label="Start time (optional)" type="time" :error="form.errors.start_time" />
                    <UiInput v-model="form.end_time" label="End time (optional)" type="time" :error="form.errors.end_time" />
                    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
                        <p class="font-medium text-slate-700 dark:text-slate-300">First structure approver</p>
                        <p class="mt-1 text-slate-600 dark:text-slate-400">
                            {{
                                approver
                                    ? `${approver.name} (${approver.staff_id})`
                                    : 'No structure approver found — request will go directly to HR'
                            }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Uses the leave approval workflow from Global settings. HR always gives the final decision.
                        </p>
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
                <UiButton type="submit" variant="primary" :disabled="form.processing">Submit request</UiButton>
            </div>
        </form>
    </AppLayout>
</template>
