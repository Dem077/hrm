<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';

type RoleListItem = {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
    is_system: boolean;
};

defineProps<{
    roles: RoleListItem[];
}>();

const { can } = usePermissions();

function destroyRole(id: number, name: string) {
    if (!confirm(`Delete role "${name}"? Users must be reassigned first.`)) {
        return;
    }

    router.delete(`/roles/${id}`);
}
</script>

<template>
    <Head title="Roles & Access" />

    <AppLayout>
        <PageHeader
            title="Roles & Access"
            description="Define what each role can view and manage across the application."
        >
            <template #actions>
                <UiButton v-if="can('roles.create')" href="/roles/create" variant="primary">Add role</UiButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="roles.length === 0"
            title="No roles yet"
            description="Create roles and assign permissions to control access to pages and actions."
        >
            <template #icon>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4" />
                </svg>
            </template>
            <template v-if="can('roles.create')" #action>
                <UiButton href="/roles/create" variant="primary">Add role</UiButton>
            </template>
        </EmptyState>

        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="role in roles"
                :key="role.id"
                class="rounded-xl border border-slate-200 bg-surface p-4 shadow-sm transition hover:border-brand-500/30 dark:border-slate-800 dark:hover:border-brand-500/20"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold text-slate-900 dark:text-white">{{ role.name }}</h2>
                        <p v-if="role.is_system" class="mt-0.5 text-xs text-brand-600 dark:text-brand-400">System role — always full access</p>
                    </div>
                    <span
                        v-if="role.is_system"
                        class="shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950/60 dark:text-brand-300"
                    >
                        System
                    </span>
                </div>

                <dl class="mt-3 space-y-1.5 text-xs">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Permissions</dt>
                        <dd class="text-slate-700 dark:text-slate-300">{{ role.permissions_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Users</dt>
                        <dd class="text-slate-700 dark:text-slate-300">{{ role.users_count }}</dd>
                    </div>
                </dl>

                <div class="mt-3 flex gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <UiButton v-if="can('roles.update')" size="sm" :href="`/roles/${role.id}/edit`" variant="secondary">
                        {{ role.is_system ? 'View' : 'Edit' }}
                    </UiButton>
                    <UiButton
                        v-if="can('roles.delete') && !role.is_system"
                        size="sm"
                        variant="ghost"
                        class="text-red-600 hover:text-red-700 dark:text-red-400"
                        @click="destroyRole(role.id, role.name)"
                    >
                        Delete
                    </UiButton>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
