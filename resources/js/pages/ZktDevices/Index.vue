<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import StatusBadge from '@/components/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ZktDevice } from '@/types/zkt';

defineProps<{
    devices: ZktDevice[];
}>();

function formatDate(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
}

function syncAll() {
    router.post('/zkt-devices/sync-all');
}

function syncDevice(id: number) {
    router.post(`/zkt-devices/${id}/sync`, {}, { preserveScroll: true });
}

function testDevice(id: number) {
    router.post(`/zkt-devices/${id}/test`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="ZKT Devices" />

    <AppLayout title="ZKT Devices">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-stone-500">Manage biometric punch machines and sync attendance.</p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-100"
                    @click="syncAll"
                >
                    Sync All Active
                </button>
                <Link
                    href="/zkt-devices/create"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                >
                    Add Device
                </Link>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50 text-left text-stone-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Address</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Last Sync</th>
                        <th class="px-4 py-3 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="device in devices" :key="device.id ?? device.name">
                        <td class="px-4 py-3">
                            <Link :href="`/zkt-devices/${device.id}`" class="font-medium text-amber-700 hover:underline">
                                {{ device.name }}
                            </Link>
                            <p v-if="device.location" class="text-xs text-stone-500">{{ device.location }}</p>
                        </td>
                        <td class="px-4 py-3">
                            {{ device.ip_address }}:{{ device.port }}
                            <p class="text-xs uppercase text-stone-500">{{ device.protocol }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge
                                :status="device.connection_status"
                                :label="device.connection_status_label ?? device.connection_status"
                                :color="device.connection_status_color"
                            />
                        </td>
                        <td class="px-4 py-3">{{ formatDate(device.last_synced_at) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-md border border-stone-300 px-2.5 py-1 text-xs hover:bg-stone-50"
                                    @click="testDevice(device.id!)"
                                >
                                    Test
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-amber-300 px-2.5 py-1 text-xs text-amber-800 hover:bg-amber-50"
                                    @click="syncDevice(device.id!)"
                                >
                                    Sync
                                </button>
                                <Link
                                    :href="`/zkt-devices/${device.id}/edit`"
                                    class="rounded-md border border-stone-300 px-2.5 py-1 text-xs hover:bg-stone-50"
                                >
                                    Edit
                                </Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="devices.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-stone-500">
                            No devices yet.
                            <Link href="/zkt-devices/create" class="text-amber-700 hover:underline">Add your first device</Link>.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
