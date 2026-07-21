<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import EmployeeAvatar from '@/components/ui/EmployeeAvatar.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatDateTime } from '@/lib/format';
import type { Employee } from '@/types/hrm';

defineProps<{
    employee: Employee;
}>();

const { can } = usePermissions();

function deleteEmployee(id: number) {
    if (confirm('Delete this employee record?')) {
        router.delete(`/employees/${id}`);
    }
}

function syncDevices(id: number) {
    router.post(`/employees/${id}/sync-devices`, {}, { preserveScroll: true });
}

function pullDeviceCredentials(id: number) {
    router.post(`/employees/${id}/pull-device-credentials`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="employee.name" />

    <AppLayout>
        <PageHeader :title="employee.name" :description="`${employee.staff_id} · ${employee.gender_label}`">
            <template #actions>
                <UiButton v-if="can('employees.update')" :href="`/employees/${employee.id}/edit`" variant="secondary">Edit</UiButton>
                <UiButton v-if="can('employees.delete')" variant="danger" @click="deleteEmployee(employee.id!)">Delete</UiButton>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <UiCard title="Profile">
                <div class="mb-5 flex items-center gap-4 border-b border-slate-100 pb-5 dark:border-slate-800">
                    <EmployeeAvatar :photo-url="employee.profile_photo_url" :name="employee.name" size="md" />
                    <div>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ employee.name }}</p>
                        <p class="text-sm text-slate-500">{{ employee.staff_id }}</p>
                    </div>
                </div>

                <dl class="space-y-4 text-sm">
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Staff ID</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.staff_id }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">National ID</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.national_id }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Mobile</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.mobile_number ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Joined</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDate(employee.joined_date) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Duty type</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.duty_type_label ?? employee.duty_type }}</dd>
                    </div>
                    <div v-if="employee.duty_type === 'normal'" class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Saturday work</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.works_saturday ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Duty times</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">
                            <template v-if="employee.duty_type === 'shift'">
                                Duty roster
                            </template>
                            <template v-else-if="employee.uses_custom_duty_times">
                                Custom:
                                {{ employee.custom_duty_start_time ?? '—' }}–{{ employee.custom_duty_end_time ?? '—' }}
                                <span v-if="employee.custom_grace_minutes !== null"> · {{ employee.custom_grace_minutes }}m grace</span>
                            </template>
                            <template v-else>Global policy</template>
                        </dd>
                    </div>
                </dl>
            </UiCard>

            <UiCard title="Organization & approvals">
                <dl class="space-y-4 text-sm">
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Grade</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">
                            <div v-if="employee.grade">{{ employee.grade.label }}</div>
                            <div v-if="employee.grade?.path_label" class="mt-1 text-xs font-normal text-slate-500">
                                {{ employee.grade.path_label }}
                            </div>
                            <span v-if="!employee.grade">—</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Direct manager</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            <UiButton
                                v-if="employee.manager"
                                size="sm"
                                variant="ghost"
                                :href="`/employees/${employee.manager.id}`"
                            >
                                {{ employee.manager.name }}
                            </UiButton>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Login email</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Login status</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            {{ employee.has_login ? 'Active account' : 'No account' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Access roles</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            {{ (employee.role_names ?? []).length ? employee.role_names!.join(', ') : '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Status</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.is_active ? 'Active' : 'Inactive' }}</dd>
                    </div>
                </dl>
            </UiCard>
        </div>

        <div class="mt-6">
            <UiCard title="Profile details" description="Extended employee profile, contact, and banking information.">
                <div class="grid gap-6 lg:grid-cols-2">
                    <dl class="space-y-4 text-sm">
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Current address</dt>
                            <dd class="max-w-xs text-right font-medium text-slate-900 dark:text-slate-100">{{ employee.current_address ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Permanent address</dt>
                            <dd class="max-w-xs text-right font-medium text-slate-900 dark:text-slate-100">{{ employee.permanent_address ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Ext No</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.ext_no ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Personal email</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.personal_email ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Office email</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.office_email ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Emergency contact</dt>
                            <dd class="text-right font-medium text-slate-900 dark:text-slate-100">
                                <template v-if="employee.emergency_contact_name || employee.emergency_contact_number">
                                    {{ employee.emergency_contact_name ?? '—' }}
                                    <span v-if="employee.emergency_contact_number" class="block text-xs text-slate-500">{{ employee.emergency_contact_number }}</span>
                                </template>
                                <span v-else>—</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Length of service</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.length_of_service_label ?? '—' }}</dd>
                        </div>
                    </dl>

                    <dl class="space-y-4 text-sm">
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Date of birth</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDate(employee.date_of_birth) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Marital status</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.marital_status_label ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Blood group</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.blood_group_label ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Nationality</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.nationality ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Religion</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.religion ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Work location</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.work_location ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Qualification</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.qualification ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Employment type</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.employment_type_label ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Bank name</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.bank_name_label ?? employee.bank_name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500">Account name</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.account_name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Account no</dt>
                            <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.account_no ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </UiCard>
        </div>

        <div v-if="(employee.direct_reports ?? []).length" class="mt-6">
            <UiCard title="Direct reports" description="Employees who report to this person for approvals.">
                <div class="space-y-2">
                    <UiButton
                        v-for="report in employee.direct_reports"
                        :key="report.id"
                        size="sm"
                        variant="ghost"
                        :href="`/employees/${report.id}`"
                    >
                        {{ report.name }} ({{ report.staff_id }})
                    </UiButton>
                </div>
            </UiCard>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <UiCard title="Machine access groups">
                <dl class="space-y-4 text-sm">
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Device privilege</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            {{ employee.device_privilege_label ?? employee.device_privilege ?? 'Employee' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Card number</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            {{ employee.device_card_number ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Device password</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            {{ employee.has_device_password ? 'Set' : '—' }}
                        </dd>
                    </div>
                </dl>
                <div v-if="(employee.zkt_location_groups ?? []).length" class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="group in employee.zkt_location_groups"
                        :key="group.id"
                        class="rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700 dark:bg-brand-950/40 dark:text-brand-300"
                    >
                        {{ group.name }}
                    </span>
                </div>
                <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                    No location groups assigned. Edit the employee to choose which machines they can access.
                </p>
                <div v-if="can('zkt-devices.manage-users')" class="mt-4 flex flex-wrap gap-2">
                    <UiButton size="sm" variant="secondary" @click="pullDeviceCredentials(employee.id!)">
                        Pull from machine
                    </UiButton>
                    <UiButton size="sm" variant="primary" @click="syncDevices(employee.id!)">
                        Sync to machines
                    </UiButton>
                </div>
            </UiCard>

            <UiCard title="Machine profile sync status" padding="none">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Machine</th>
                                <th class="px-5 py-3.5 font-medium">Status</th>
                                <th class="px-5 py-3.5 font-medium">Last synced</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="sync in employee.zkt_device_syncs ?? []" :key="sync.id">
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ sync.device?.name ?? '—' }}</p>
                                    <p v-if="sync.last_error" class="text-xs text-red-600 dark:text-red-400">{{ sync.last_error }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <UiBadge :label="sync.sync_status_label" :color="sync.sync_status_color" />
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ formatDateTime(sync.last_synced_at) }}
                                </td>
                            </tr>
                            <tr v-if="!(employee.zkt_device_syncs ?? []).length">
                                <td colspan="3" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">
                                    No machine sync records yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>
        </div>
    </AppLayout>
</template>
