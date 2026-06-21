<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ZktLocationGroup } from '@/types/hrm';

const props = defineProps<{
    locationGroups: ZktLocationGroup[];
    devices: Array<{ id: number; name: string; location: string | null; machine_type?: string; machine_type_label?: string }>;
    emptyLocationGroup: ZktLocationGroup;
}>();

const { can } = usePermissions();
const editingId = ref<number | null>(null);

const form = useForm({
    name: props.emptyLocationGroup.name,
    code: props.emptyLocationGroup.code ?? '',
    description: props.emptyLocationGroup.description ?? '',
    sort_order: props.emptyLocationGroup.sort_order,
    is_active: props.emptyLocationGroup.is_active,
    device_ids: [...(props.emptyLocationGroup.device_ids ?? [])],
});

function resetForm() {
    editingId.value = null;
    form.reset();
    form.name = props.emptyLocationGroup.name;
    form.code = props.emptyLocationGroup.code ?? '';
    form.description = props.emptyLocationGroup.description ?? '';
    form.sort_order = props.emptyLocationGroup.sort_order;
    form.is_active = props.emptyLocationGroup.is_active;
    form.device_ids = [...(props.emptyLocationGroup.device_ids ?? [])];
    form.clearErrors();
}

function editGroup(group: ZktLocationGroup) {
    editingId.value = group.id;
    form.name = group.name;
    form.code = group.code ?? '';
    form.description = group.description ?? '';
    form.sort_order = group.sort_order;
    form.is_active = group.is_active;
    form.device_ids = [...(group.device_ids ?? group.devices?.map((device) => device.id) ?? [])];
    form.clearErrors();
}

function toggleDevice(deviceId: number) {
    const index = form.device_ids.indexOf(deviceId);

    if (index >= 0) {
        form.device_ids.splice(index, 1);
        return;
    }

    form.device_ids.push(deviceId);
}

function submit() {
    if (editingId.value) {
        form.put(`/zkt-location-groups/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post('/zkt-location-groups', {
        preserveScroll: true,
        onSuccess: resetForm,
    });
}

function destroyGroup(id: number, name: string) {
    if (confirm(`Delete location group "${name}"? Assigned employees will lose access to machines in this group.`)) {
        router.delete(`/zkt-location-groups/${id}`, { preserveScroll: true });
    }
}

function syncGroupUsers(id: number) {
    router.post(`/zkt-location-groups/${id}/sync-users`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Machine Location Groups" />

    <AppLayout>
        <PageHeader
            title="Machine location groups"
            description="Group machines by location and control access by assigned employees."
        />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
            <UiCard padding="none">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Group</th>
                                <th class="px-5 py-3.5 font-medium">Machines</th>
                                <th class="px-5 py-3.5 font-medium">Employees</th>
                                <th class="px-5 py-3.5 font-medium">Status</th>
                                <th class="px-5 py-3.5 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="group in locationGroups" :key="group.id!">
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ group.name }}</p>
                                    <p v-if="group.code" class="text-xs text-slate-500">{{ group.code }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ group.devices_count ?? group.devices?.length ?? 0 }}
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ group.employees_count ?? 0 }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            group.is_active
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                        "
                                    >
                                        {{ group.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <UiButton
                                            v-if="can('zkt-location-groups.update')"
                                            size="sm"
                                            variant="ghost"
                                            @click="editGroup(group)"
                                        >
                                            Edit
                                        </UiButton>
                                        <UiButton
                                            v-if="can('zkt-location-groups.sync-users')"
                                            size="sm"
                                            variant="secondary"
                                            @click="syncGroupUsers(group.id!)"
                                        >
                                            Sync users
                                        </UiButton>
                                        <UiButton
                                            v-if="can('zkt-location-groups.delete')"
                                            size="sm"
                                            variant="danger"
                                            @click="destroyGroup(group.id!, group.name)"
                                        >
                                            Delete
                                        </UiButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="locationGroups.length === 0">
                                <td colspan="5" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">
                                    No location groups yet. Create one to assign machines and employee access.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>

            <UiCard
                v-if="can('zkt-location-groups.create') || can('zkt-location-groups.update')"
                :title="editingId ? 'Edit location group' : 'Add location group'"
                description="Assign machines to this group. Employees get access when assigned on their profile."
            >
                <form class="space-y-5" @submit.prevent="submit">
                    <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
                    <UiInput v-model="form.code" label="Code" hint="Optional short code" :error="form.errors.code" />
                    <UiInput v-model="form.description" label="Description" :error="form.errors.description" />
                    <UiInput v-model="form.sort_order" label="Sort order" type="number" min="0" :error="form.errors.sort_order" />

                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 bg-white text-brand-600 dark:border-slate-600 dark:bg-surface dark:text-brand-500" />
                        Group is active
                    </label>

                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Machines in this group</p>
                        <div class="max-h-56 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <label
                                v-for="device in devices"
                                :key="device.id"
                                class="flex items-start gap-3 rounded-lg px-2 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-surface-muted"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 bg-white text-brand-600 dark:border-slate-600 dark:bg-surface dark:text-brand-500"
                                    :checked="form.device_ids.includes(device.id)"
                                    @change="toggleDevice(device.id)"
                                />
                                <span>
                                    <span class="font-medium">{{ device.name }}</span>
                                    <span class="block text-xs text-slate-500">
                                        {{ device.location ?? 'No location' }}
                                        · {{ device.machine_type_label ?? device.machine_type_short_label ?? 'Attendance machine' }}
                                    </span>
                                </span>
                            </label>
                            <p v-if="devices.length === 0" class="text-sm text-slate-500">Add attendance machines first.</p>
                        </div>
                        <p v-if="form.errors.device_ids" class="mt-1 text-sm text-red-600">{{ form.errors.device_ids }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <UiButton type="submit" variant="primary" :disabled="form.processing">
                            {{ editingId ? 'Save changes' : 'Create group' }}
                        </UiButton>
                        <UiButton v-if="editingId" type="button" variant="ghost" @click="resetForm">Cancel</UiButton>
                    </div>
                </form>
            </UiCard>
        </div>
    </AppLayout>
</template>
