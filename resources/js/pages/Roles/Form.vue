<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';

type RoleFormData = {
    id: number | null;
    name: string;
    permissions: string[];
    is_system: boolean;
};

const props = defineProps<{
    role: RoleFormData;
    permissionGroups: Record<string, Record<string, string>>;
}>();

const isEditing = computed(() => props.role.id !== null);
const isSystemRole = computed(() => props.role.is_system);

const form = useForm({
    name: props.role.name,
    permissions: [...props.role.permissions],
});

function togglePermission(permission: string) {
    if (isSystemRole.value) {
        return;
    }

    const index = form.permissions.indexOf(permission);

    if (index >= 0) {
        form.permissions.splice(index, 1);
        return;
    }

    form.permissions.push(permission);
}

function toggleGroup(groupPermissions: Record<string, string>) {
    if (isSystemRole.value) {
        return;
    }

    const keys = Object.keys(groupPermissions);
    const allSelected = keys.every((key) => form.permissions.includes(key));

    if (allSelected) {
        form.permissions = form.permissions.filter((permission) => !keys.includes(permission));
        return;
    }

    const merged = new Set([...form.permissions, ...keys]);
    form.permissions = [...merged];
}

function isGroupFullySelected(groupPermissions: Record<string, string>): boolean {
    return Object.keys(groupPermissions).every((key) => form.permissions.includes(key));
}

function isGroupPartiallySelected(groupPermissions: Record<string, string>): boolean {
    const keys = Object.keys(groupPermissions);
    const selected = keys.filter((key) => form.permissions.includes(key));

    return selected.length > 0 && selected.length < keys.length;
}

function submit() {
    if (isEditing.value) {
        form.put(`/roles/${props.role.id}`);
        return;
    }

    form.post('/roles');
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Role' : 'Add Role'" />

    <AppLayout>
        <PageHeader
            :title="isEditing ? (isSystemRole ? 'Super Admin role' : 'Edit role') : 'Add role'"
            :description="
                isSystemRole
                    ? 'This system role always has every permission. You can review access here but cannot change it.'
                    : 'Choose a name and select which pages and actions this role may access.'
            "
        >
            <template #actions>
                <UiButton href="/roles" variant="ghost">Back to roles</UiButton>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <UiCard title="Role details">
                <UiInput
                    v-model="form.name"
                    label="Role name"
                    required
                    :disabled="isSystemRole"
                    :error="form.errors.name"
                />
            </UiCard>

            <UiCard
                title="Permissions"
                :description="
                    isSystemRole
                        ? 'All permissions are enabled for Super Admin.'
                        : 'Grant access by module. Users only see sidebar items they can view.'
                "
            >
                <p v-if="form.errors.permissions" class="mb-4 text-sm text-red-600 dark:text-red-400">
                    {{ form.errors.permissions }}
                </p>

                <div class="space-y-5">
                    <section
                        v-for="(groupPermissions, groupName) in permissionGroups"
                        :key="groupName"
                        class="rounded-xl border border-slate-200 dark:border-slate-800"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                            :class="isSystemRole ? 'cursor-default' : 'hover:bg-slate-50 dark:hover:bg-surface-elevated'"
                            :disabled="isSystemRole"
                            @click="toggleGroup(groupPermissions)"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ groupName }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ Object.keys(groupPermissions).filter((key) => form.permissions.includes(key) || isSystemRole).length }}
                                    /
                                    {{ Object.keys(groupPermissions).length }} selected
                                </p>
                            </div>
                            <span
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded border text-xs"
                                :class="
                                    isSystemRole || isGroupFullySelected(groupPermissions)
                                        ? 'border-brand-500 bg-brand-600 text-white'
                                        : isGroupPartiallySelected(groupPermissions)
                                          ? 'border-brand-500 bg-brand-600/20 text-brand-700 dark:text-brand-300'
                                          : 'border-slate-300 dark:border-slate-600'
                                "
                            >
                                <svg v-if="isSystemRole || isGroupFullySelected(groupPermissions)" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                                <span v-else-if="isGroupPartiallySelected(groupPermissions)">−</span>
                            </span>
                        </button>

                        <div class="grid gap-2 border-t border-slate-100 px-4 py-3 sm:grid-cols-2 dark:border-slate-800">
                            <label
                                v-for="(label, permission) in groupPermissions"
                                :key="permission"
                                class="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2 text-sm transition hover:bg-slate-50 dark:hover:bg-surface-elevated"
                                :class="isSystemRole ? 'cursor-default opacity-80' : ''"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-brand-600 dark:border-slate-600 dark:bg-surface"
                                    :checked="isSystemRole || form.permissions.includes(permission)"
                                    :disabled="isSystemRole"
                                    @change="togglePermission(permission)"
                                />
                                <span>
                                    <span class="block font-medium text-slate-800 dark:text-slate-200">{{ label }}</span>
                                    <span class="block text-xs text-slate-500">{{ permission }}</span>
                                </span>
                            </label>
                        </div>
                    </section>
                </div>
            </UiCard>

            <div v-if="!isSystemRole" class="flex justify-end">
                <UiButton type="submit" variant="primary" :disabled="form.processing">
                    {{ isEditing ? 'Update role' : 'Create role' }}
                </UiButton>
            </div>
        </form>
    </AppLayout>
</template>
