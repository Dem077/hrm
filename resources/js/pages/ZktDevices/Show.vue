<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import StatusBadge from '@/components/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ZktDevice } from '@/types/zkt';

defineProps<{
    device: ZktDevice;
}>();

function formatDate(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
}

function testDevice(id: number) {
    router.post(`/zkt-devices/${id}/test`, {}, { preserveScroll: true });
}

function syncDevice(id: number) {
    router.post(`/zkt-devices/${id}/sync`, {}, { preserveScroll: true });
}

function deleteDevice(id: number) {
    if (confirm('Delete this device and all related punch logs?')) {
        router.delete(`/zkt-devices/${id}`);
    }
}
</script>

<template>
    <Head :title="device.name" />

    <AppLayout :title="device.name">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <StatusBadge
                    :status="device.connection_status"
                    :label="device.connection_status_label ?? device.connection_status"
                    :color="device.connection_status_color"
                />
                <p class="mt-2 text-sm text-stone-500">
                    {{ device.ip_address }}:{{ device.port }} · {{ device.protocol_label ?? device.protocol }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-stone-300 px-4 py-2 text-sm hover:bg-stone-100"
                    @click="testDevice(device.id!)"
                >
                    Test Connection
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-amber-300 px-4 py-2 text-sm text-amber-800 hover:bg-amber-50"
                    @click="syncDevice(device.id!)"
                >
                    Sync Attendance
                </button>
                <Link
                    :href="`/zkt-devices/${device.id}/edit`"
                    class="rounded-lg border border-stone-300 px-4 py-2 text-sm hover:bg-stone-100"
                >
                    Edit
                </Link>
                <button
                    type="button"
                    class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50"
                    @click="deleteDevice(device.id!)"
                >
                    Delete
                </button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-medium">Device Info</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-500">Serial</dt>
                        <dd>{{ device.serial_number ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-500">Model</dt>
                        <dd>{{ device.model_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-500">Firmware</dt>
                        <dd>{{ device.firmware_version ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-500">Last Connected</dt>
                        <dd>{{ formatDate(device.last_connected_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-500">Last Synced</dt>
                        <dd>{{ formatDate(device.last_synced_at) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-medium">Sync History</h2>
                <div class="space-y-3">
                    <div
                        v-for="log in device.sync_logs ?? []"
                        :key="log.id"
                        class="rounded-lg border border-stone-100 px-4 py-3 text-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <StatusBadge :status="log.status" :label="log.status_label" :color="log.status_color" />
                            <span class="text-xs text-stone-500">{{ formatDate(log.started_at) }}</span>
                        </div>
                        <p class="mt-2 text-stone-700">{{ log.message }}</p>
                        <p class="mt-1 text-xs text-stone-500">
                            Fetched {{ log.records_fetched }} · Stored {{ log.records_stored }}
                        </p>
                    </div>
                    <p v-if="!(device.sync_logs ?? []).length" class="text-sm text-stone-500">No sync history yet.</p>
                </div>
            </section>
        </div>

        <section class="mt-6 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">Recent Punches</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-stone-500">
                        <tr>
                            <th class="px-3 py-2">User ID</th>
                            <th class="px-3 py-2">State</th>
                            <th class="px-3 py-2">Punched At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in device.attendance_logs ?? []" :key="log.id" class="border-t border-stone-100">
                            <td class="px-3 py-2">{{ log.device_user_id }}</td>
                            <td class="px-3 py-2">{{ log.punch_state_label }}</td>
                            <td class="px-3 py-2">{{ formatDate(log.punched_at) }}</td>
                        </tr>
                        <tr v-if="!(device.attendance_logs ?? []).length">
                            <td colspan="3" class="px-3 py-6 text-center text-stone-500">No punches synced yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
