<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { AttendanceDutyPolicy, PayrollPeriodSettings, PublicHoliday } from '@/types/attendance';

const props = defineProps<{
    policies: AttendanceDutyPolicy[];
    holidays: PublicHoliday[];
    year: number;
    payrollPeriod: PayrollPeriodSettings;
    emptyPolicy: AttendanceDutyPolicy;
    emptyHoliday: Omit<PublicHoliday, 'id'>;
}>();

const { can } = usePermissions();

const yearFilter = reactive({ year: props.year });
const editingPolicyId = ref<number | null>(null);

const policyForm = useForm({ ...props.emptyPolicy });
const holidayForm = useForm({ ...props.emptyHoliday });
const payrollForm = useForm({
    payroll_period_start_day: props.payrollPeriod.payroll_period_start_day,
});

function applyYear() {
    router.get('/attendance-settings', yearFilter, {
        preserveState: true,
        replace: true,
    });
}

function submitPolicy() {
    if (editingPolicyId.value) {
        policyForm.put(`/attendance-settings/duty-policies/${editingPolicyId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                editingPolicyId.value = null;
                policyForm.reset();
                policyForm.defaults({ ...props.emptyPolicy });
            },
        });

        return;
    }

    policyForm.post('/attendance-settings/duty-policies', {
        preserveScroll: true,
        onSuccess: () => {
            policyForm.reset();
            policyForm.defaults({ ...props.emptyPolicy });
        },
    });
}

function editPolicy(policy: AttendanceDutyPolicy) {
    editingPolicyId.value = policy.id ?? null;
    policyForm.effective_from = policy.effective_from;
    policyForm.duty_start_time = policy.duty_start_time;
    policyForm.duty_end_time = policy.duty_end_time;
    policyForm.grace_minutes = policy.grace_minutes;
    policyForm.saturday_duty_start_time = policy.saturday_duty_start_time;
    policyForm.saturday_duty_end_time = policy.saturday_duty_end_time;
    policyForm.saturday_grace_minutes = policy.saturday_grace_minutes;
}

function cancelPolicyEdit() {
    editingPolicyId.value = null;
    policyForm.reset();
    policyForm.defaults({ ...props.emptyPolicy });
}

function deletePolicy(id: number) {
    if (confirm('Delete this duty policy?')) {
        router.delete(`/attendance-settings/duty-policies/${id}`, { preserveScroll: true });
    }
}

function submitHoliday() {
    holidayForm.post('/attendance-settings/holidays', {
        preserveScroll: true,
        onSuccess: () => {
            holidayForm.reset();
            holidayForm.defaults({ ...props.emptyHoliday });
        },
    });
}

function deleteHoliday(id: number, name: string) {
    if (confirm(`Remove "${name}" from public holidays?`)) {
        router.delete(`/attendance-settings/holidays/${id}`, { preserveScroll: true });
    }
}

function submitPayrollPeriod() {
    payrollForm.put('/attendance-settings/payroll-period', {
        preserveScroll: true,
    });
}

const payrollEndDayLabel = computed(() => {
    const startDay = payrollForm.payroll_period_start_day;

    if (startDay <= 1) {
        return 'the last day of the following month';
    }

    return `the ${startDay - 1}${ordinalSuffix(startDay - 1)} of the following month`;
});

function ordinalSuffix(day: number): string {
    if (day >= 11 && day <= 13) {
        return 'th';
    }

    return ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'][day % 10];
}
</script>

<template>
    <Head title="Attendance Settings" />

    <AppLayout>
        <PageHeader
            title="Global settings"
            description="Weekdays use regular duty times. Friday and Saturday are holidays unless an employee is marked as working Saturday. Public holidays apply on their dates."
        />

        <div class="space-y-6">
            <UiCard
                title="Payroll period"
                description="Defines the monthly payroll cycle used as the default date range on the attendance sheet."
            >
                <form
                    v-if="can('attendance-settings.payroll-period.update')"
                    class="grid gap-4 md:grid-cols-[12rem_1fr_auto] md:items-end"
                    @submit.prevent="submitPayrollPeriod"
                >
                    <UiInput
                        v-model="payrollForm.payroll_period_start_day"
                        label="Period starts on day"
                        type="number"
                        min="1"
                        max="28"
                        required
                        :error="payrollForm.errors.payroll_period_start_day"
                    />
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Example with day {{ payrollForm.payroll_period_start_day }}:
                        each period runs from the {{ payrollForm.payroll_period_start_day }}{{ ordinalSuffix(Number(payrollForm.payroll_period_start_day)) }}
                        through {{ payrollEndDayLabel }}.
                        Current period: {{ payrollPeriod.current.label }}.
                    </p>
                    <UiButton type="submit" variant="primary" :disabled="payrollForm.processing">Save</UiButton>
                </form>
                <p v-else class="text-sm text-slate-600 dark:text-slate-400">
                    Period starts on day {{ payrollPeriod.payroll_period_start_day }}.
                    Current period: {{ payrollPeriod.current.label }}.
                </p>
            </UiCard>

            <UiCard title="Duty policies" description="Regular duty times apply Sun–Thu. Saturday duty times apply only to employees marked as working Saturday. The latest policy on or before each date is used.">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-3 font-medium">Effective from</th>
                                <th class="px-3 py-3 font-medium">Weekday start</th>
                                <th class="px-3 py-3 font-medium">Weekday end</th>
                                <th class="px-3 py-3 font-medium">Grace</th>
                                <th class="px-3 py-3 font-medium">Sat start</th>
                                <th class="px-3 py-3 font-medium">Sat end</th>
                                <th class="px-3 py-3 font-medium">Sat grace</th>
                                <th class="px-3 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="policy in policies" :key="policy.id!">
                                <td class="px-3 py-3 text-slate-700 dark:text-slate-300">{{ formatDate(policy.effective_from) }}</td>
                                <td class="px-3 py-3">{{ policy.duty_start_time }}</td>
                                <td class="px-3 py-3">{{ policy.duty_end_time }}</td>
                                <td class="px-3 py-3">{{ policy.grace_minutes }} min</td>
                                <td class="px-3 py-3">{{ policy.saturday_duty_start_time }}</td>
                                <td class="px-3 py-3">{{ policy.saturday_duty_end_time }}</td>
                                <td class="px-3 py-3">{{ policy.saturday_grace_minutes }} min</td>
                                <td class="px-3 py-3">
                                    <div v-if="can('attendance-settings.duty-policies.update') || can('attendance-settings.duty-policies.delete')" class="flex gap-2">
                                        <UiButton v-if="can('attendance-settings.duty-policies.update')" size="sm" variant="ghost" @click="editPolicy(policy)">Edit</UiButton>
                                        <UiButton v-if="can('attendance-settings.duty-policies.delete')" size="sm" variant="danger" @click="deletePolicy(policy.id!)">Delete</UiButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    v-if="can('attendance-settings.duty-policies.create') || can('attendance-settings.duty-policies.update')"
                    class="mt-6 space-y-4 border-t border-slate-100 pt-6 dark:border-slate-800"
                    @submit.prevent="submitPolicy"
                >
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Weekday duty (Sun–Thu)</p>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <UiInput v-model="policyForm.effective_from" label="Effective from" type="date" required :error="policyForm.errors.effective_from" />
                        <UiInput v-model="policyForm.duty_start_time" label="Duty start" type="time" required :error="policyForm.errors.duty_start_time" />
                        <UiInput v-model="policyForm.duty_end_time" label="Duty end" type="time" required :error="policyForm.errors.duty_end_time" />
                        <UiInput v-model="policyForm.grace_minutes" label="Grace (minutes)" type="number" min="0" max="180" required :error="policyForm.errors.grace_minutes" />
                    </div>

                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Saturday duty (employees who work Saturday)</p>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <UiInput v-model="policyForm.saturday_duty_start_time" label="Saturday start" type="time" required :error="policyForm.errors.saturday_duty_start_time" />
                        <UiInput v-model="policyForm.saturday_duty_end_time" label="Saturday end" type="time" required :error="policyForm.errors.saturday_duty_end_time" />
                        <UiInput v-model="policyForm.saturday_grace_minutes" label="Saturday grace (minutes)" type="number" min="0" max="180" required :error="policyForm.errors.saturday_grace_minutes" />
                    </div>

                    <div class="flex gap-2">
                        <UiButton type="submit" variant="primary" :disabled="policyForm.processing">
                            {{ editingPolicyId ? 'Update policy' : 'Add policy' }}
                        </UiButton>
                        <UiButton v-if="editingPolicyId" type="button" variant="ghost" @click="cancelPolicyEdit">Cancel</UiButton>
                    </div>
                </form>
            </UiCard>

            <UiCard title="Public holidays" description="Extra holidays on specific dates. Every Friday and Saturday are already treated as holidays unless an employee works Saturday.">
                <form class="mb-6 flex flex-wrap items-end gap-4" @submit.prevent="applyYear">
                    <UiInput v-model="yearFilter.year" label="Year" type="number" min="2000" max="2100" class="w-40" />
                    <UiButton type="submit" variant="secondary">Show year</UiButton>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            <tr>
                                <th class="px-3 py-3 font-medium">Date</th>
                                <th class="px-3 py-3 font-medium">Name</th>
                                <th class="px-3 py-3 font-medium">Notes</th>
                                <th class="px-3 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="holiday in holidays" :key="holiday.id!">
                                <td class="px-3 py-3 text-slate-700 dark:text-slate-300">{{ formatDate(holiday.date) }}</td>
                                <td class="px-3 py-3 font-medium text-slate-900 dark:text-slate-100">{{ holiday.name }}</td>
                                <td class="px-3 py-3 text-slate-600 dark:text-slate-400">{{ holiday.notes ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <UiButton
                                        v-if="can('attendance-settings.holidays.delete')"
                                        size="sm"
                                        variant="danger"
                                        @click="deleteHoliday(holiday.id!, holiday.name)"
                                    >
                                        Delete
                                    </UiButton>
                                </td>
                            </tr>
                            <tr v-if="holidays.length === 0">
                                <td colspan="4" class="px-3 py-8 text-center text-slate-500">No holidays for {{ year }}.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    v-if="can('attendance-settings.holidays.create')"
                    class="mt-6 grid gap-4 border-t border-slate-100 pt-6 dark:border-slate-800 md:grid-cols-2 xl:grid-cols-4"
                    @submit.prevent="submitHoliday"
                >
                    <UiInput v-model="holidayForm.name" label="Name" required :error="holidayForm.errors.name" />
                    <UiInput v-model="holidayForm.date" label="Date" type="date" required :error="holidayForm.errors.date" />
                    <UiInput v-model="holidayForm.notes" label="Notes" :error="holidayForm.errors.notes" />
                    <div class="flex items-end">
                        <UiButton type="submit" variant="primary" :disabled="holidayForm.processing">Add holiday</UiButton>
                    </div>
                </form>
            </UiCard>
        </div>
    </AppLayout>
</template>
