<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Department } from '@/types/hrm';

defineProps<{
    department: Department;
}>();

function deleteDepartment(id: number) {
    if (confirm('Delete this department?')) {
        router.delete(`/departments/${id}`);
    }
}
</script>

<template>
    <Head :title="department.name" />

    <AppLayout>
        <PageHeader
            :title="department.name"
            :description="department.code ? `Code: ${department.code}` : 'Department profile'"
        >
            <template #actions>
                <UiButton :href="`/departments/${department.id}/edit`" variant="secondary">Edit</UiButton>
                <UiButton variant="danger" @click="deleteDepartment(department.id!)">Delete</UiButton>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <UiCard title="Department info">
                <dl class="space-y-4 text-sm">
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Department head</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            <UiButton
                                v-if="department.head_employee"
                                size="sm"
                                variant="ghost"
                                :href="`/employees/${department.head_employee.id}`"
                            >
                                {{ department.head_employee.name }}
                            </UiButton>
                            <span v-else>—</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Employees</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ department.employees_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Status</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ department.is_active ? 'Active' : 'Inactive' }}</dd>
                    </div>
                </dl>
                <p v-if="department.description" class="mt-4 text-sm text-slate-600 dark:text-slate-400">
                    {{ department.description }}
                </p>
            </UiCard>

            <UiCard title="Approval flow">
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Requests from employees in this department can route to their
                    <strong class="font-medium text-slate-800 dark:text-slate-200">direct manager</strong>
                    first, then escalate to the
                    <strong class="font-medium text-slate-800 dark:text-slate-200">department head</strong>
                    when needed.
                </p>
            </UiCard>
        </div>

        <div class="mt-6">
            <UiCard title="Assigned employees" padding="none">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Staff ID</th>
                                <th class="px-5 py-3 font-medium">Name</th>
                                <th class="px-5 py-3 font-medium">Direct manager</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="employee in department.employees ?? []" :key="employee.id">
                                <td class="px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">{{ employee.staff_id }}</td>
                                <td class="px-5 py-3">
                                    <UiButton size="sm" variant="ghost" :href="`/employees/${employee.id}`">
                                        {{ employee.name }}
                                    </UiButton>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-400">
                                    {{ employee.manager?.name ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="!(department.employees ?? []).length">
                                <td colspan="3" class="px-5 py-8 text-center text-slate-500">No employees assigned yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>
        </div>
    </AppLayout>
</template>
