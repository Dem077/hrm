<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Department } from '@/types/hrm';

defineProps<{
    departments: Department[];
}>();

const { can } = usePermissions();
</script>

<template>
    <Head title="Departments" />

    <AppLayout>
        <PageHeader
            title="Departments"
            description="Flat team list with a department head for approvals."
        >
            <template #actions>
                <UiButton v-if="can('departments.create')" href="/departments/create" variant="primary">Add department</UiButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="departments.length === 0"
            title="No departments yet"
            description="Create departments and assign a head for approval routing."
        >
            <template #icon>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4" />
                </svg>
            </template>
            <template v-if="can('departments.create')" #action>
                <UiButton href="/departments/create" variant="primary">Add department</UiButton>
            </template>
        </EmptyState>

        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="department in departments"
                :key="department.id!"
                class="rounded-xl border border-slate-200 bg-surface p-4 shadow-sm transition hover:border-brand-500/30 dark:border-slate-800 dark:hover:border-brand-500/20"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <Link
                            :href="`/departments/${department.id}`"
                            class="block truncate font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                        >
                            {{ department.name }}
                        </Link>
                        <p v-if="department.code" class="mt-0.5 text-xs text-slate-500">{{ department.code }}</p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            department.is_active
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                        "
                    >
                        {{ department.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <dl class="mt-3 space-y-1.5 text-xs">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Head</dt>
                        <dd class="truncate text-slate-700 dark:text-slate-300">{{ department.head_employee?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Employees</dt>
                        <dd class="text-slate-700 dark:text-slate-300">{{ department.employees_count }}</dd>
                    </div>
                </dl>

                <div class="mt-3 flex gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <UiButton size="sm" :href="`/departments/${department.id}`" variant="ghost">View</UiButton>
                    <UiButton v-if="can('departments.update')" size="sm" :href="`/departments/${department.id}/edit`" variant="secondary">Edit</UiButton>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
