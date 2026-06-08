<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
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
                        <dt class="text-slate-500">Saturday work</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ employee.works_saturday ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Duty times</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">
                            <template v-if="employee.uses_custom_duty_times">
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
                        <dt class="text-slate-500">Department</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            <UiButton
                                v-if="employee.department"
                                size="sm"
                                variant="ghost"
                                :href="`/departments/${employee.department.id}`"
                            >
                                {{ employee.department.name }}
                            </UiButton>
                            <span v-else>—</span>
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
    </AppLayout>
</template>
