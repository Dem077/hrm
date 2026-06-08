<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import type { ZktDevice } from '@/types/zkt';

defineProps<{
    device: ZktDevice;
}>();

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

    <AppLayout>
        <PageHeader
            :title="device.name"
            :description="`${device.ip_address}:${device.port} · ${device.protocol_label ?? device.protocol}`"
        >
            <template #actions>
                <UiBadge
                    :label="device.connection_status_label ?? device.connection_status"
                    :color="device.connection_status_color"
                />
                <UiButton variant="secondary" @click="testDevice(device.id!)">Test</UiButton>
                <UiButton variant="primary" @click="syncDevice(device.id!)">Sync</UiButton>
                <UiButton :href="`/zkt-devices/${device.id}/edit`" variant="ghost">Edit</UiButton>
                <UiButton variant="danger" @click="deleteDevice(device.id!)">Delete</UiButton>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <UiCard title="Device info">
                <dl class="space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                        <dt class="text-slate-500">Serial</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-100">{{ device.serial_number ?? '—' }}</dd>
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
            <UiCard title="Recent punches" padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">User ID</th>
                            <th class="px-5 py-3.5 font-medium">State</th>
                            <th class="px-5 py-3.5 font-medium">Punched at</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="log in device.attendance_logs ?? []" :key="log.id">
                            <td class="px-5 py-4 font-medium text-slate-800 dark:text-slate-200">{{ log.device_user_id }}</td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ log.punch_state_label }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(log.punched_at) }}</td>
                        </tr>
                        <tr v-if="!(device.attendance_logs ?? []).length">
                            <td colspan="3" class="px-5 py-10 text-center text-slate-500">No punches synced yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </UiCard>
        </div>
    </AppLayout>
</template>
