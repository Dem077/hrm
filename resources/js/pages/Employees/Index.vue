<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { Employee } from '@/types/hrm';

defineProps<{
    employees: Employee[];
}>();

const { can } = usePermissions();
</script>

<template>
    <Head title="Employees" />

    <AppLayout>
        <PageHeader
            title="Employees"
            description="Manage staff records, department assignments, and reporting lines for approvals."
        >
            <template #actions>
                <UiButton v-if="can('employees.create')" href="/employees/create" variant="primary">Add employee</UiButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="employees.length === 0"
            title="No employees yet"
            description="Add your first employee to start building departments and approval hierarchies."
        >
            <template #icon>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4" />
                </svg>
            </template>
            <template v-if="can('employees.create')" #action>
                <UiButton href="/employees/create" variant="primary">Add employee</UiButton>
            </template>
        </EmptyState>

        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="employee in employees"
                :key="employee.id ?? employee.staff_id"
                class="rounded-xl border border-slate-200 bg-surface p-4 shadow-sm transition hover:border-brand-500/30 dark:border-slate-800 dark:hover:border-brand-500/20"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600/15 text-sm font-semibold text-brand-700 dark:text-brand-400">
                        {{ employee.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="`/employees/${employee.id}`"
                            class="block truncate font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                        >
                            {{ employee.name }}
                        </Link>
                        <p class="text-xs text-slate-500">{{ employee.staff_id }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            employee.is_active
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                        "
                    >
                        {{ employee.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <dl class="mt-3 space-y-1.5 text-xs">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Department</dt>
                        <dd class="truncate text-slate-700 dark:text-slate-300">{{ employee.department?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Manager</dt>
                        <dd class="truncate text-slate-700 dark:text-slate-300">{{ employee.manager?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Joined</dt>
                        <dd class="text-slate-700 dark:text-slate-300">{{ formatDate(employee.joined_date) }}</dd>
                    </div>
                </dl>

                <div class="mt-3 flex gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <UiButton size="sm" :href="`/employees/${employee.id}`" variant="ghost">View</UiButton>
                    <UiButton v-if="can('employees.update')" size="sm" :href="`/employees/${employee.id}/edit`" variant="secondary">Edit</UiButton>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
