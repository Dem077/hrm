<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import BanksCard from '@/pages/AttendanceSettings/components/BanksCard.vue';
import type { BankRow } from '@/pages/AttendanceSettings/components/BanksCard.vue';
import { formatDate } from '@/lib/format';
import type { AttendanceDutyPolicy, PayrollPeriodSettings, PublicHoliday } from '@/types/attendance';

type SettingsTab = 'payroll' | 'leave' | 'duty' | 'holidays';

const props = defineProps<{
    leaveCarryForwardEnabled: boolean;
    leaveApprovalWorkflow: Array<{
        key: string;
        label: string;
        description: string;
        enabled: boolean;
        locked: boolean;
    }>;
    banks: BankRow[];
    policies: AttendanceDutyPolicy[];
    tempPolicies: AttendanceDutyPolicy[];
    holidays: PublicHoliday[];
    year: number;
    payrollPeriod: PayrollPeriodSettings;
    emptyPolicy: AttendanceDutyPolicy;
    emptyTempPolicy: AttendanceDutyPolicy;
    emptyHoliday: Omit<PublicHoliday, 'id'>;
}>();

const { can } = usePermissions();

const tabs: Array<{ id: SettingsTab; label: string }> = [
    { id: 'payroll', label: 'Payroll' },
    { id: 'leave', label: 'Leave' },
    { id: 'duty', label: 'Duty policies' },
    { id: 'holidays', label: 'Public holidays' },
];

const activeTab = ref<SettingsTab>('payroll');

const yearFilter = reactive({ year: props.year });
const editingPolicyId = ref<number | null>(null);
const editingTempPolicyId = ref<number | null>(null);
const payrollModalOpen = ref(false);
const policyModalOpen = ref(false);
const tempPolicyModalOpen = ref(false);
const holidayModalOpen = ref(false);

const policyForm = useForm({ ...props.emptyPolicy, is_temporary: false });
const tempPolicyForm = useForm({ ...props.emptyTempPolicy, is_temporary: true });
const holidayForm = useForm({ ...props.emptyHoliday });
const payrollForm = useForm({
    payroll_period_start_day: props.payrollPeriod.payroll_period_start_day,
});
const leaveCarryForwardForm = useForm({
    leave_carry_forward_enabled: props.leaveCarryForwardEnabled ? '1' : '0',
});
const leaveWorkflowForm = useForm({
    steps: props.leaveApprovalWorkflow.map((step) => ({
        key: step.key,
        enabled: step.enabled,
        label: step.label,
        description: step.description,
        locked: step.locked,
    })),
});

function focusTab(tabId: SettingsTab) {
    activeTab.value = tabId;
}

function applyYear() {
    router.get('/attendance-settings', yearFilter, {
        preserveState: true,
        replace: true,
    });
}

function openPayrollModal() {
    payrollForm.payroll_period_start_day = props.payrollPeriod.payroll_period_start_day;
    payrollForm.clearErrors();
    payrollModalOpen.value = true;
}

function closePayrollModal() {
    payrollModalOpen.value = false;
    payrollForm.reset();
    payrollForm.defaults({
        payroll_period_start_day: props.payrollPeriod.payroll_period_start_day,
    });
}

function submitPayrollPeriod() {
    payrollForm.put('/attendance-settings/payroll-period', {
        preserveScroll: true,
        onSuccess: closePayrollModal,
    });
}

function submitLeaveCarryForwardSetting() {
    leaveCarryForwardForm.put('/attendance-settings/leave-carry-forward', {
        preserveScroll: true,
        forceFormData: true,
    });
}

function submitLeaveWorkflowSetting() {
    leaveWorkflowForm
        .transform((data) => ({
            steps: data.steps
                .filter((step) => step.key !== 'hr')
                .map((step) => ({
                    key: step.key,
                    enabled: step.enabled,
                })),
        }))
        .put('/attendance-settings/leave-approval-workflow', {
            preserveScroll: true,
        });
}

function moveWorkflowStep(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= leaveWorkflowForm.steps.length) {
        return;
    }

    if (leaveWorkflowForm.steps[index]?.locked || leaveWorkflowForm.steps[target]?.locked) {
        return;
    }

    const steps = [...leaveWorkflowForm.steps];
    const [item] = steps.splice(index, 1);
    steps.splice(target, 0, item);
    leaveWorkflowForm.steps = steps;
}

function openAddPolicyModal() {
    editingPolicyId.value = null;
    policyForm.reset();
    policyForm.defaults({ ...props.emptyPolicy, is_temporary: false });
    policyForm.is_temporary = false;
    policyForm.clearErrors();
    policyModalOpen.value = true;
}

function editPolicy(policy: AttendanceDutyPolicy) {
    editingPolicyId.value = policy.id ?? null;
    policyForm.is_temporary = false;
    policyForm.name = policy.name ?? '';
    policyForm.effective_from = policy.effective_from;
    policyForm.effective_until = null;
    policyForm.duty_start_time = policy.duty_start_time;
    policyForm.duty_end_time = policy.duty_end_time;
    policyForm.grace_minutes = policy.grace_minutes;
    policyForm.saturday_duty_start_time = policy.saturday_duty_start_time;
    policyForm.saturday_duty_end_time = policy.saturday_duty_end_time;
    policyForm.saturday_grace_minutes = policy.saturday_grace_minutes;
    policyForm.clearErrors();
    policyModalOpen.value = true;
}

function closePolicyModal() {
    policyModalOpen.value = false;
    editingPolicyId.value = null;
    policyForm.reset();
    policyForm.defaults({ ...props.emptyPolicy, is_temporary: false });
}

function submitPolicy() {
    policyForm.is_temporary = false;

    if (editingPolicyId.value) {
        policyForm.put(`/attendance-settings/duty-policies/${editingPolicyId.value}`, {
            preserveScroll: true,
            onSuccess: closePolicyModal,
        });

        return;
    }

    policyForm.post('/attendance-settings/duty-policies', {
        preserveScroll: true,
        onSuccess: closePolicyModal,
    });
}

function openAddTempPolicyModal() {
    editingTempPolicyId.value = null;
    tempPolicyForm.reset();
    tempPolicyForm.defaults({ ...props.emptyTempPolicy, is_temporary: true });
    tempPolicyForm.is_temporary = true;
    tempPolicyForm.clearErrors();
    tempPolicyModalOpen.value = true;
}

function editTempPolicy(policy: AttendanceDutyPolicy) {
    editingTempPolicyId.value = policy.id ?? null;
    tempPolicyForm.is_temporary = true;
    tempPolicyForm.name = policy.name ?? '';
    tempPolicyForm.effective_from = policy.effective_from;
    tempPolicyForm.effective_until = policy.effective_until ?? '';
    tempPolicyForm.duty_start_time = policy.duty_start_time;
    tempPolicyForm.duty_end_time = policy.duty_end_time;
    tempPolicyForm.grace_minutes = policy.grace_minutes;
    tempPolicyForm.saturday_duty_start_time = policy.saturday_duty_start_time;
    tempPolicyForm.saturday_duty_end_time = policy.saturday_duty_end_time;
    tempPolicyForm.saturday_grace_minutes = policy.saturday_grace_minutes;
    tempPolicyForm.clearErrors();
    tempPolicyModalOpen.value = true;
}

function closeTempPolicyModal() {
    tempPolicyModalOpen.value = false;
    editingTempPolicyId.value = null;
    tempPolicyForm.reset();
    tempPolicyForm.defaults({ ...props.emptyTempPolicy, is_temporary: true });
}

function submitTempPolicy() {
    tempPolicyForm.is_temporary = true;

    if (editingTempPolicyId.value) {
        tempPolicyForm.put(`/attendance-settings/duty-policies/${editingTempPolicyId.value}`, {
            preserveScroll: true,
            onSuccess: closeTempPolicyModal,
        });

        return;
    }

    tempPolicyForm.post('/attendance-settings/duty-policies', {
        preserveScroll: true,
        onSuccess: closeTempPolicyModal,
    });
}

function deletePolicy(id: number, isTemporary = false) {
    const message = isTemporary
        ? 'Delete this temporary duty policy?'
        : 'Delete this permanent duty policy?';

    if (confirm(message)) {
        router.delete(`/attendance-settings/duty-policies/${id}`, { preserveScroll: true });
    }
}

function openHolidayModal() {
    holidayForm.reset();
    holidayForm.defaults({ ...props.emptyHoliday });
    holidayForm.clearErrors();
    holidayModalOpen.value = true;
}

function closeHolidayModal() {
    holidayModalOpen.value = false;
    holidayForm.reset();
    holidayForm.defaults({ ...props.emptyHoliday });
}

function submitHoliday() {
    holidayForm.post('/attendance-settings/holidays', {
        preserveScroll: true,
        onSuccess: closeHolidayModal,
    });
}

function deleteHoliday(id: number, name: string) {
    if (confirm(`Remove "${name}" from public holidays?`)) {
        router.delete(`/attendance-settings/holidays/${id}`, { preserveScroll: true });
    }
}

function ordinalSuffix(day: number): string {
    if (day >= 11 && day <= 13) {
        return 'th';
    }

    return ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'][day % 10];
}

function formatDayOrdinal(day: number): string {
    return `${day}${ordinalSuffix(day)}`;
}

function payrollEndLabel(startDay: number, endDay: number | null): string {
    if (startDay <= 1 || endDay === null) {
        return 'Last day of next month';
    }

    return `${formatDayOrdinal(endDay)} of next month`;
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
            <div class="overflow-x-auto border-b border-slate-200 dark:border-slate-800">
                <div class="flex min-w-max gap-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="relative whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition"
                        :class="
                            activeTab === tab.id
                                ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-300'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200'
                        "
                        @click="focusTab(tab.id)"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div v-show="activeTab === 'payroll'" class="space-y-6">
                <UiCard
                    title="Payroll period"
                    description="Defines the monthly payroll cycle used as the default date range on the attendance sheet."
                >
                    <template #actions>
                        <UiButton
                            v-if="can('attendance-settings.payroll-period.update')"
                            size="sm"
                            variant="secondary"
                            @click="openPayrollModal"
                        >
                            Edit
                        </UiButton>
                    </template>

                    <dl class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-surface-elevated">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Starts</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                {{ formatDayOrdinal(payrollPeriod.start_day) }} of month
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-surface-elevated">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Ends</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                {{ payrollEndLabel(payrollPeriod.start_day, payrollPeriod.end_day) }}
                            </dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-surface-elevated">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Current period</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                {{ payrollPeriod.current.label }}
                            </dd>
                        </div>
                    </dl>
                </UiCard>

                <BanksCard
                    :banks="banks"
                    :can-manage="can('attendance-settings.payroll-period.update')"
                />
            </div>

            <div v-show="activeTab === 'leave'" class="space-y-6">
                <UiCard
                    title="Leave policies"
                    description="Global controls for leave behavior across the system."
                >
                    <form class="space-y-4" @submit.prevent="submitLeaveCarryForwardSetting">
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <p class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">Carry forward</p>
                            <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-surface-elevated">
                                <label class="cursor-pointer">
                                    <input
                                        v-model="leaveCarryForwardForm.leave_carry_forward_enabled"
                                        type="radio"
                                        value="1"
                                        class="sr-only"
                                        :disabled="!can('attendance-settings.leave-carry-forward.update')"
                                    />
                                    <span
                                        class="inline-flex rounded-md px-4 py-1.5 text-sm"
                                        :class="
                                            leaveCarryForwardForm.leave_carry_forward_enabled === '1'
                                                ? 'bg-brand-600 text-white'
                                                : 'text-slate-600 dark:text-slate-300'
                                        "
                                    >
                                        On
                                    </span>
                                </label>
                                <label class="cursor-pointer">
                                    <input
                                        v-model="leaveCarryForwardForm.leave_carry_forward_enabled"
                                        type="radio"
                                        value="0"
                                        class="sr-only"
                                        :disabled="!can('attendance-settings.leave-carry-forward.update')"
                                    />
                                    <span
                                        class="inline-flex rounded-md px-4 py-1.5 text-sm"
                                        :class="
                                            leaveCarryForwardForm.leave_carry_forward_enabled === '0'
                                                ? 'bg-brand-600 text-white'
                                                : 'text-slate-600 dark:text-slate-300'
                                        "
                                    >
                                        Off
                                    </span>
                                </label>
                            </div>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                Controls whether carry-forward options appear in Leave Types.
                            </p>
                        </div>
                        <div v-if="can('attendance-settings.leave-carry-forward.update')" class="flex justify-end">
                            <UiButton type="submit" variant="primary" :disabled="leaveCarryForwardForm.processing">
                                Save
                            </UiButton>
                        </div>
                    </form>
                </UiCard>

                <UiCard
                    title="Leave approval workflow"
                    description="Choose how leave and overtime move through the company structure before final HR approval. Steps without a matching head are skipped automatically."
                >
                    <form class="space-y-4" @submit.prevent="submitLeaveWorkflowSetting">
                        <ol class="space-y-2">
                            <li
                                v-for="(step, index) in leaveWorkflowForm.steps"
                                :key="step.key"
                                class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <label class="flex min-w-0 flex-1 items-start gap-3">
                                        <input
                                            v-model="step.enabled"
                                            type="checkbox"
                                            class="mt-1 rounded border-slate-300"
                                            :disabled="step.locked || !can('attendance-settings.leave-workflow.update')"
                                        />
                                        <span>
                                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                                                {{ index + 1 }}. {{ step.label }}
                                            </span>
                                            <span class="mt-0.5 block text-xs text-slate-500">{{ step.description }}</span>
                                        </span>
                                    </label>
                                    <div v-if="!step.locked && can('attendance-settings.leave-workflow.update')" class="flex gap-1">
                                        <UiButton
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            :disabled="index === 0"
                                            @click="moveWorkflowStep(index, -1)"
                                        >
                                            Up
                                        </UiButton>
                                        <UiButton
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            :disabled="index >= leaveWorkflowForm.steps.filter((item) => !item.locked).length - 1"
                                            @click="moveWorkflowStep(index, 1)"
                                        >
                                            Down
                                        </UiButton>
                                    </div>
                                </div>
                            </li>
                        </ol>
                        <p v-if="leaveWorkflowForm.errors.steps" class="text-xs text-red-600 dark:text-red-400">
                            {{ leaveWorkflowForm.errors.steps }}
                        </p>
                        <div v-if="can('attendance-settings.leave-workflow.update')" class="flex justify-end">
                            <UiButton type="submit" variant="primary" :disabled="leaveWorkflowForm.processing">
                                Save workflow
                            </UiButton>
                        </div>
                    </form>
                </UiCard>
            </div>

            <div v-show="activeTab === 'duty'">
                <UiCard
                    title="Duty policies"
                    description="Regular duty times come from permanent policies. Temporary overrides apply for limited periods and take priority over permanent settings."
                >
                    <div class="space-y-6">
                        <section class="rounded-xl border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-surface-elevated">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Permanent duty policy</h3>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        Applies from the effective date onward until a newer permanent policy starts.
                                    </p>
                                </div>
                                <UiButton
                                    v-if="can('attendance-settings.duty-policies.create')"
                                    size="sm"
                                    variant="primary"
                                    @click="openAddPolicyModal"
                                >
                                    Add policy
                                </UiButton>
                            </div>
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
                                                    <UiButton v-if="can('attendance-settings.duty-policies.delete')" size="sm" variant="danger" @click="deletePolicy(policy.id!, false)">Delete</UiButton>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="rounded-xl border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-surface-elevated">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Temporary duty override</h3>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        Use for short periods (e.g. Ramadan). Overrides permanent policy in the selected date range.
                                    </p>
                                </div>
                                <UiButton
                                    v-if="can('attendance-settings.duty-policies.create')"
                                    size="sm"
                                    variant="primary"
                                    @click="openAddTempPolicyModal"
                                >
                                    Add override
                                </UiButton>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="border-b border-slate-100 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                        <tr>
                                            <th class="px-3 py-3 font-medium">Period</th>
                                            <th class="px-3 py-3 font-medium">Name</th>
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
                                        <tr v-for="policy in tempPolicies" :key="policy.id!">
                                            <td class="px-3 py-3 text-slate-700 dark:text-slate-300">{{ policy.period_label }}</td>
                                            <td class="px-3 py-3 font-medium text-slate-900 dark:text-slate-100">{{ policy.name || '—' }}</td>
                                            <td class="px-3 py-3">{{ policy.duty_start_time }}</td>
                                            <td class="px-3 py-3">{{ policy.duty_end_time }}</td>
                                            <td class="px-3 py-3">{{ policy.grace_minutes }} min</td>
                                            <td class="px-3 py-3">{{ policy.saturday_duty_start_time }}</td>
                                            <td class="px-3 py-3">{{ policy.saturday_duty_end_time }}</td>
                                            <td class="px-3 py-3">{{ policy.saturday_grace_minutes }} min</td>
                                            <td class="px-3 py-3">
                                                <div v-if="can('attendance-settings.duty-policies.update') || can('attendance-settings.duty-policies.delete')" class="flex gap-2">
                                                    <UiButton v-if="can('attendance-settings.duty-policies.update')" size="sm" variant="ghost" @click="editTempPolicy(policy)">Edit</UiButton>
                                                    <UiButton v-if="can('attendance-settings.duty-policies.delete')" size="sm" variant="danger" @click="deletePolicy(policy.id!, true)">Delete</UiButton>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="tempPolicies.length === 0">
                                            <td colspan="9" class="px-3 py-8 text-center text-slate-500">No temporary duty overrides configured.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </UiCard>
            </div>

            <div v-show="activeTab === 'holidays'">
                <UiCard title="Public holidays" description="Extra holidays on specific dates. Every Friday and Saturday are already treated as holidays unless an employee works Saturday.">
                    <template #actions>
                        <UiButton
                            v-if="can('attendance-settings.holidays.create')"
                            size="sm"
                            variant="primary"
                            @click="openHolidayModal"
                        >
                            Add holiday
                        </UiButton>
                    </template>

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
                </UiCard>
            </div>
        </div>

        <UiModal
            :open="payrollModalOpen"
            title="Edit payroll period"
            description="Defines the monthly payroll cycle used as the default date range on the attendance sheet."
            max-width="md"
            @close="closePayrollModal"
        >
            <form id="payroll-period-form" class="space-y-4" @submit.prevent="submitPayrollPeriod">
                <UiInput
                    v-model="payrollForm.payroll_period_start_day"
                    label="Start day of month"
                    type="number"
                    min="1"
                    max="28"
                    required
                    :error="payrollForm.errors.payroll_period_start_day"
                />
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm dark:border-slate-800 dark:bg-surface-elevated">
                    <p class="text-slate-500 dark:text-slate-400">Preview</p>
                    <p class="mt-1 font-medium text-slate-900 dark:text-slate-100">
                        {{ formatDayOrdinal(Number(payrollForm.payroll_period_start_day)) }} of month
                        →
                        {{
                            payrollEndLabel(
                                Number(payrollForm.payroll_period_start_day),
                                Number(payrollForm.payroll_period_start_day) > 1
                                    ? Number(payrollForm.payroll_period_start_day) - 1
                                    : null,
                            )
                        }}
                    </p>
                </div>
            </form>

            <template #footer>
                <UiButton type="button" variant="ghost" @click="closePayrollModal">Cancel</UiButton>
                <UiButton type="submit" form="payroll-period-form" variant="primary" :disabled="payrollForm.processing">Save</UiButton>
            </template>
        </UiModal>

        <UiModal
            :open="policyModalOpen"
            :title="editingPolicyId ? 'Edit permanent duty policy' : 'Add permanent duty policy'"
            description="Applies from the effective date onward until a newer permanent policy starts."
            max-width="xl"
            @close="closePolicyModal"
        >
            <form id="duty-policy-form" class="space-y-5" @submit.prevent="submitPolicy">
                <div>
                    <p class="mb-4 text-sm font-medium text-slate-700 dark:text-slate-300">Weekday duty (Sun–Thu)</p>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <UiInput v-model="policyForm.effective_from" label="Effective from" type="date" required :error="policyForm.errors.effective_from" />
                        <UiInput v-model="policyForm.duty_start_time" label="Duty start" type="time" required :error="policyForm.errors.duty_start_time" />
                        <UiInput v-model="policyForm.duty_end_time" label="Duty end" type="time" required :error="policyForm.errors.duty_end_time" />
                        <UiInput v-model="policyForm.grace_minutes" label="Grace (minutes)" type="number" min="0" max="180" required :error="policyForm.errors.grace_minutes" />
                    </div>
                </div>

                <div>
                    <p class="mb-4 text-sm font-medium text-slate-700 dark:text-slate-300">Saturday duty (employees who work Saturday)</p>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <UiInput v-model="policyForm.saturday_duty_start_time" label="Saturday start" type="time" required :error="policyForm.errors.saturday_duty_start_time" />
                        <UiInput v-model="policyForm.saturday_duty_end_time" label="Saturday end" type="time" required :error="policyForm.errors.saturday_duty_end_time" />
                        <UiInput v-model="policyForm.saturday_grace_minutes" label="Saturday grace (minutes)" type="number" min="0" max="180" required :error="policyForm.errors.saturday_grace_minutes" />
                    </div>
                </div>
            </form>

            <template #footer>
                <UiButton type="button" variant="ghost" @click="closePolicyModal">Cancel</UiButton>
                <UiButton type="submit" form="duty-policy-form" variant="primary" :disabled="policyForm.processing">
                    {{ editingPolicyId ? 'Update policy' : 'Add policy' }}
                </UiButton>
            </template>
        </UiModal>

        <UiModal
            :open="tempPolicyModalOpen"
            :title="editingTempPolicyId ? 'Edit temporary duty override' : 'Add temporary duty override'"
            description="Overrides permanent duty policies for the selected date range only."
            max-width="xl"
            @close="closeTempPolicyModal"
        >
            <form id="temp-duty-policy-form" class="space-y-5" @submit.prevent="submitTempPolicy">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <UiInput v-model="tempPolicyForm.name" label="Name" hint="Optional label, e.g. Ramadan hours." :error="tempPolicyForm.errors.name" />
                    <UiInput v-model="tempPolicyForm.effective_from" label="Period starts" type="date" required :error="tempPolicyForm.errors.effective_from" />
                    <UiInput v-model="tempPolicyForm.effective_until" label="Period ends" type="date" required :error="tempPolicyForm.errors.effective_until" />
                </div>

                <div>
                    <p class="mb-4 text-sm font-medium text-slate-700 dark:text-slate-300">Weekday duty (Sun–Thu)</p>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <UiInput v-model="tempPolicyForm.duty_start_time" label="Duty start" type="time" required :error="tempPolicyForm.errors.duty_start_time" />
                        <UiInput v-model="tempPolicyForm.duty_end_time" label="Duty end" type="time" required :error="tempPolicyForm.errors.duty_end_time" />
                        <UiInput v-model="tempPolicyForm.grace_minutes" label="Grace (minutes)" type="number" min="0" max="180" required :error="tempPolicyForm.errors.grace_minutes" />
                    </div>
                </div>

                <div>
                    <p class="mb-4 text-sm font-medium text-slate-700 dark:text-slate-300">Saturday duty (employees who work Saturday)</p>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <UiInput v-model="tempPolicyForm.saturday_duty_start_time" label="Saturday start" type="time" required :error="tempPolicyForm.errors.saturday_duty_start_time" />
                        <UiInput v-model="tempPolicyForm.saturday_duty_end_time" label="Saturday end" type="time" required :error="tempPolicyForm.errors.saturday_duty_end_time" />
                        <UiInput v-model="tempPolicyForm.saturday_grace_minutes" label="Saturday grace (minutes)" type="number" min="0" max="180" required :error="tempPolicyForm.errors.saturday_grace_minutes" />
                    </div>
                </div>
            </form>

            <template #footer>
                <UiButton type="button" variant="ghost" @click="closeTempPolicyModal">Cancel</UiButton>
                <UiButton type="submit" form="temp-duty-policy-form" variant="primary" :disabled="tempPolicyForm.processing">
                    {{ editingTempPolicyId ? 'Update override' : 'Add override' }}
                </UiButton>
            </template>
        </UiModal>

        <UiModal
            :open="holidayModalOpen"
            title="Add public holiday"
            description="Extra holidays on specific dates. Every Friday and Saturday are already treated as holidays unless an employee works Saturday."
            max-width="md"
            @close="closeHolidayModal"
        >
            <form id="holiday-form" class="grid gap-4" @submit.prevent="submitHoliday">
                <UiInput v-model="holidayForm.name" label="Name" required :error="holidayForm.errors.name" />
                <UiInput v-model="holidayForm.date" label="Date" type="date" required :error="holidayForm.errors.date" />
                <UiInput v-model="holidayForm.notes" label="Notes" :error="holidayForm.errors.notes" />
            </form>

            <template #footer>
                <UiButton type="button" variant="ghost" @click="closeHolidayModal">Cancel</UiButton>
                <UiButton type="submit" form="holiday-form" variant="primary" :disabled="holidayForm.processing">Add holiday</UiButton>
            </template>
        </UiModal>
    </AppLayout>
</template>
