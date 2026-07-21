<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import type { ZktDevice } from '@/types/zkt';

defineProps<{
    device: ZktDevice;
}>();

const { can } = usePermissions();

function testDevice(id: number) {
    router.post(`/zkt-devices/${id}/test`, {}, { preserveScroll: true });
}

function syncDevice(id: number) {
    router.post(`/zkt-devices/${id}/sync`, {}, { preserveScroll: true });
}

function readDeviceTime(id: number) {
    router.post(`/zkt-devices/${id}/read-time`, {}, { preserveScroll: true });
}

function syncDeviceTime(id: number) {
    router.post(`/zkt-devices/${id}/sync-time`, {}, { preserveScroll: true });
}

function syncDeviceUsers(id: number) {
    router.post(`/zkt-devices/${id}/sync-users`, {}, { preserveScroll: true });
}

function pullDeviceUsers(id: number) {
    router.post(`/zkt-devices/${id}/pull-users`, {}, { preserveScroll: true });
}

function deleteDevice(id: number) {
    if (confirm('Delete this machine and all related punch logs?')) {
        router.delete(`/zkt-devices/${id}`);
    }
}
</script>

<template>
    <Head :title="device.name" />

    <AppLayout>
        <PageHeader
            :title="device.name"
            :description="
                device.connection_mode === 'adms_push'
                    ? `${device.brand_label ?? device.brand} · ADMS · SN ${device.serial_number ?? '—'}`
                    : `${device.brand_label ?? device.brand} · ${device.ip_address}:${device.port} · ${device.protocol_label ?? device.protocol}`
            "
        >
            <template #actions>
                <UiBadge
                    :label="device.connection_status_label ?? device.connection_status"
                    :color="device.connection_status_color"
                />
            </template>
        </PageHeader>

        <section
            class="mb-6 rounded-2xl border border-slate-200 bg-surface p-4 shadow-sm shadow-slate-200/50 dark:border-slate-800 dark:shadow-black/20"
            aria-label="Device actions"
        >
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-if="device.connection_mode !== 'adms_push'" class="space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Connection</p>
                    <div class="flex flex-wrap gap-2">
                        <UiButton v-if="can('zkt-devices.test')" size="sm" variant="secondary" @click="testDevice(device.id!)">Test</UiButton>
                    </div>
                </div>

                <div v-if="device.connection_mode !== 'adms_push'" class="space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Clock</p>
                    <div class="flex flex-wrap gap-2">
                        <UiButton v-if="can('zkt-devices.read-time')" size="sm" variant="secondary" @click="readDeviceTime(device.id!)">Read time</UiButton>
                        <UiButton v-if="can('zkt-devices.sync-time')" size="sm" variant="secondary" @click="syncDeviceTime(device.id!)">Sync time</UiButton>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">User profiles</p>
                    <div class="flex flex-wrap gap-2">
                        <UiButton v-if="can('zkt-devices.manage-users')" size="sm" variant="primary" @click="syncDeviceUsers(device.id!)">
                            {{ device.connection_mode === 'adms_push' ? 'Queue user sync' : 'Sync assigned users' }}
                        </UiButton>
                        <UiButton v-if="can('zkt-devices.manage-users')" size="sm" variant="secondary" @click="pullDeviceUsers(device.id!)">
                            {{ device.connection_mode === 'adms_push' ? 'Query users from device' : 'Pull employee credentials' }}
                        </UiButton>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Attendance</p>
                    <div class="flex flex-wrap gap-2">
                        <UiButton
                            v-if="can('zkt-devices.sync') && device.connection_mode !== 'adms_push'"
                            size="sm"
                            variant="primary"
                            @click="syncDevice(device.id!)"
                        >
                            Sync punches
                        </UiButton>
                        <p v-if="device.connection_mode === 'adms_push'" class="text-sm text-slate-500 dark:text-slate-400">
                            Punches arrive via ADMS push.
                        </p>
                    </div>
                </div>

                <div class="space-y-2 sm:col-span-2 xl:col-span-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Manage</p>
                    <div class="flex flex-wrap gap-2">
                        <UiButton v-if="can('zkt-devices.update')" size="sm" :href="`/zkt-devices/${device.id}/edit`" variant="ghost">Edit</UiButton>
                        <UiButton v-if="can('zkt-devices.delete')" size="sm" variant="danger" @click="deleteDevice(device.id!)">Delete</UiButton>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <UiCard title="Machine details">
                <dl class="space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Brand</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ device.brand_label ?? device.brand }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Machine type</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            {{ device.machine_type_label ?? device.machine_type }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Connection mode</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ device.connection_mode_label ?? device.connection_mode }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Serial</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ device.serial_number ?? '—' }}</dd>
                    </div>
                    <div
                        v-if="device.connection_mode === 'adms_push'"
                        class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800"
                    >
                        <dt class="text-slate-500">Last ADMS contact</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(device.last_adms_seen_at) }}</dd>
                    </div>
                    <div
                        v-if="device.connection_mode === 'adms_push'"
                        class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800"
                    >
                        <dt class="text-slate-500">ADMS commands</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">
                            {{ device.adms_pending_commands ?? 0 }} pending
                            <span v-if="(device.adms_failed_commands ?? 0) > 0" class="text-red-600 dark:text-red-400">
                                · {{ device.adms_failed_commands }} failed
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Model</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ device.model_name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Firmware</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ device.firmware_version ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Last connected</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(device.last_connected_at) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Last synced</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ formatDateTime(device.last_synced_at) }}</dd>
                    </div>
                </dl>
            </UiCard>

            <UiCard title="Sync history">
                <div class="space-y-3">
                    <div
                        v-for="log in device.sync_logs ?? []"
                        :key="log.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3 text-sm dark:border-slate-800 dark:bg-surface-elevated"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <UiBadge :label="log.status_label" :color="log.status_color" />
                            <span class="text-xs text-slate-500">{{ formatDateTime(log.started_at) }}</span>
                        </div>
                        <p class="mt-2 text-slate-700 dark:text-slate-300">{{ log.message }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Fetched {{ log.records_fetched }} · Stored {{ log.records_stored }}
                        </p>
                    </div>
                    <p v-if="!(device.sync_logs ?? []).length" class="text-sm text-slate-500">No sync history yet.</p>
                </div>
            </UiCard>
        </div>

        <div class="mt-6">
            <UiCard title="Assigned user sync status" padding="none">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Employee</th>
                                <th class="px-5 py-3.5 font-medium">Status</th>
                                <th class="px-5 py-3.5 font-medium">Last synced</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="sync in device.employee_syncs ?? []" :key="sync.id">
                                <td class="px-5 py-4">
                                    <Link
                                        v-if="sync.employee"
                                        :href="`/employees/${sync.employee.id}`"
                                        class="font-medium text-brand-700 hover:text-brand-600 hover:underline dark:text-brand-400 dark:hover:text-brand-300"
                                    >
                                        {{ sync.employee.name }}
                                    </Link>
                                    <span v-else class="text-slate-500">—</span>
                                    <p v-if="sync.last_error" class="text-xs text-red-600 dark:text-red-400">{{ sync.last_error }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <UiBadge :label="sync.sync_status_label" :color="sync.sync_status_color" />
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(sync.last_synced_at) }}</td>
                            </tr>
                            <tr v-if="!(device.employee_syncs ?? []).length">
                                <td colspan="3" class="px-5 py-10 text-center text-slate-500">No assigned user sync records yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>
        </div>

        <div v-if="device.connection_mode === 'adms_push'" class="mt-6">
            <UiCard title="ADMS command queue" padding="none">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">#</th>
                                <th class="px-5 py-3.5 font-medium">Command</th>
                                <th class="px-5 py-3.5 font-medium">Status</th>
                                <th class="px-5 py-3.5 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="command in device.adms_commands ?? []" :key="command.id">
                                <td class="px-5 py-4 font-mono text-xs">{{ command.command_no }}</td>
                                <td class="px-5 py-4 font-mono text-xs text-slate-700 dark:text-slate-300">{{ command.payload }}</td>
                                <td class="px-5 py-4">
                                    <UiBadge :label="command.status_label" :color="command.status_color" />
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(command.created_at) }}</td>
                            </tr>
                            <tr v-if="!(device.adms_commands ?? []).length">
                                <td colspan="4" class="px-5 py-10 text-center text-slate-500">No ADMS commands queued yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>
        </div>

        <div class="mt-6">
            <UiCard title="Recent punches" padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Emp No</th>
                            <th class="px-5 py-3.5 font-medium">Employee</th>
                            <th class="px-5 py-3.5 font-medium">State</th>
                            <th class="px-5 py-3.5 font-medium">Punched at</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="log in device.attendance_logs ?? []" :key="log.id">
                            <td class="px-5 py-4 font-mono text-xs text-slate-700 dark:text-slate-300">{{ log.emp_no }}</td>
                            <td class="px-5 py-4">
                                <Link
                                    v-if="log.employee"
                                    :href="`/employees/${log.employee.id}`"
                                    class="font-medium text-brand-700 hover:text-brand-600 hover:underline dark:text-brand-400 dark:hover:text-brand-300"
                                >
                                    {{ log.employee.name }}
                                </Link>
                                <span v-else class="text-slate-500">Unlinked</span>
                            </td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ log.punch_state_label }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(log.punched_at) }}</td>
                        </tr>
                        <tr v-if="!(device.attendance_logs ?? []).length">
                            <td colspan="4" class="px-5 py-10 text-center text-slate-500">No punches synced yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </UiCard>
        </div>
    </AppLayout>
</template>
