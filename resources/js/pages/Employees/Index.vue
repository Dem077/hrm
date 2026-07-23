<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import EmptyState from '@/components/ui/EmptyState.vue';
import EmployeeAvatar from '@/components/ui/EmployeeAvatar.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { Employee } from '@/types/hrm';

const props = defineProps<{
    employees: Employee[];
}>();

const { can } = usePermissions();
const searchText = ref('');

const filteredEmployees = computed(() => {
    const needle = searchText.value.trim().toLowerCase();

    if (needle === '') {
        return props.employees;
    }

    return props.employees.filter((employee) => {
        const haystack = [
            employee.name,
            employee.staff_id,
            employee.national_id,
            employee.email,
            employee.mobile_number,
            employee.manager?.name,
            employee.manager?.staff_id,
            employee.grade?.label,
            employee.grade?.path_label,
            employee.department?.name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(needle);
    });
});

const hasEmployees = computed(() => props.employees.length > 0);
const hasMatches = computed(() => filteredEmployees.value.length > 0);
</script>

<template>
    <Head title="Employees" />

    <AppLayout>
        <PageHeader
            title="Employees"
            description="Manage staff records, grade assignments, and reporting lines for approvals."
        >
            <template #actions>
                <UiButton v-if="can('employees.create')" href="/employees/create" variant="primary">Add employee</UiButton>
            </template>
        </PageHeader>

        <div v-if="hasEmployees" class="mb-4 max-w-md">
            <UiInput
                v-model="searchText"
                label="Search"
                placeholder="Name, staff ID, NID, email, or manager"
            />
            <p v-if="searchText.trim()" class="mt-2 text-xs text-slate-500">
                Showing {{ filteredEmployees.length }} of {{ employees.length }}
            </p>
        </div>

        <EmptyState
            v-if="!hasEmployees"
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

        <EmptyState
            v-else-if="!hasMatches"
            title="No matching employees"
            description="Try a different name, staff ID, or email."
        >
            <template #icon>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"
                    />
                </svg>
            </template>
            <template #action>
                <UiButton variant="ghost" @click="searchText = ''">Clear search</UiButton>
            </template>
        </EmptyState>

        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="employee in filteredEmployees"
                :key="employee.id ?? employee.staff_id"
                class="rounded-xl border border-slate-200 bg-surface p-4 shadow-sm transition hover:border-brand-500/30 dark:border-slate-800 dark:hover:border-brand-500/20"
            >
                <div class="flex items-start gap-3">
                    <EmployeeAvatar :photo-url="employee.profile_photo_url" :name="employee.name" size="sm" />
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
                        <dt class="text-slate-500">Org / Grade</dt>
                        <dd class="truncate text-slate-700 dark:text-slate-300">
                            {{ employee.grade?.label ?? employee.department?.name ?? '—' }}
                        </dd>
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
