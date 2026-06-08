<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import StatusBadge from '@/components/StatusBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProtocolOption, ZktDevice } from '@/types/zkt';

const props = defineProps<{
    device: ZktDevice;
    protocols: ProtocolOption[];
}>();

const isEditing = computed(() => props.device.id !== null);
const probeMessage = ref<string | null>(null);
const probeError = ref<string | null>(null);
const probing = ref(false);

const form = useForm({
    name: props.device.name,
    location: props.device.location ?? '',
    ip_address: props.device.ip_address,
    port: props.device.port,
    protocol: props.device.protocol,
    comm_password: props.device.comm_password,
    serial_number: props.device.serial_number,
    model_name: props.device.model_name,
    firmware_version: props.device.firmware_version,
    is_active: props.device.is_active,
    auto_sync: props.device.auto_sync,
    sync_interval_minutes: props.device.sync_interval_minutes,
    connection_status: props.device.connection_status,
    last_connected_at: props.device.last_connected_at,
    last_sync_error: props.device.last_sync_error,
    notes: props.device.notes ?? '',
    tcpmux_enabled: props.device.tcpmux_enabled,
    tcpmux_subdomain: props.device.tcpmux_subdomain,
    tcpmux_port: props.device.tcpmux_port,
});

const connectionStatusLabel = computed(() => {
    const labels: Record<string, string> = {
        unknown: 'Unknown',
        online: 'Online',
        offline: 'Offline',
    };

    return labels[form.connection_status] ?? form.connection_status;
});

const connectionStatusColor = computed(() => {
    const colors: Record<string, string> = {
        unknown: 'gray',
        online: 'success',
        offline: 'danger',
    };

    return colors[form.connection_status] ?? 'gray';
});

function getCsrfToken(): string {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

async function probeDevice() {
    probeMessage.value = null;
    probeError.value = null;
    probing.value = true;

    try {
        const response = await fetch('/zkt-devices/probe', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                ip_address: form.ip_address,
                port: form.port,
                protocol: form.protocol,
                comm_password: form.comm_password,
                tcpmux_enabled: form.tcpmux_enabled,
                tcpmux_subdomain: form.tcpmux_subdomain,
                tcpmux_port: form.tcpmux_port,
            }),
        });

        const payload = await response.json();

        if (response.ok && payload.connected) {
            form.serial_number = payload.device.serial_number;
            form.model_name = payload.device.model_name;
            form.firmware_version = payload.device.firmware_version;
            form.connection_status = payload.device.connection_status;
            form.last_connected_at = payload.device.last_connected_at;
            form.last_sync_error = null;
            probeMessage.value = payload.message;
            return;
        }

        form.connection_status = payload.device?.connection_status ?? 'offline';
        form.last_sync_error = payload.device?.last_sync_error ?? payload.message;
        probeError.value = payload.message;
    } catch {
        probeError.value = 'Unable to test the device connection.';
    } finally {
        probing.value = false;
    }
}

function submit() {
    if (isEditing.value) {
        form.put(`/zkt-devices/${props.device.id}`);
        return;
    }

    form.post('/zkt-devices');
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Device' : 'Add Device'" />

    <AppLayout :title="isEditing ? 'Edit Device' : 'Add Device'">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-stone-500">
                Enter the device network details, test the connection, then save.
            </p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-100 disabled:opacity-50"
                    :disabled="probing || !form.ip_address"
                    @click="probeDevice"
                >
                    {{ probing ? 'Testing...' : 'Test & Fetch Device Info' }}
                </button>
                <Link
                    href="/zkt-devices"
                    class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-100"
                >
                    Cancel
                </Link>
            </div>
        </div>

        <div
            v-if="probeMessage"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ probeMessage }}
        </div>
        <div
            v-if="probeError"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            {{ probeError }}
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-medium">Device Details</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Name</label>
                        <input v-model="form.name" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" required />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Location</label>
                        <input v-model="form.location" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">IP Address</label>
                        <input v-model="form.ip_address" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" required />
                        <p v-if="form.errors.ip_address" class="mt-1 text-sm text-red-600">{{ form.errors.ip_address }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Port</label>
                        <input v-model.number="form.port" type="number" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Protocol</label>
                        <select v-model="form.protocol" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2">
                            <option v-for="option in protocols" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Communication Password</label>
                        <input v-model.number="form.comm_password" type="number" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-medium">Sync Settings</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-stone-300" />
                        Active
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.auto_sync" type="checkbox" class="rounded border-stone-300" />
                        Auto Sync
                    </label>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Sync Interval (minutes)</label>
                        <input v-model.number="form.sync_interval_minutes" type="number" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-medium">Device Metadata</h2>
                <div class="mb-4">
                    <StatusBadge
                        :status="form.connection_status"
                        :label="connectionStatusLabel"
                        :color="connectionStatusColor"
                    />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Serial Number</label>
                        <input v-model="form.serial_number" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" readonly />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Model</label>
                        <input v-model="form.model_name" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" readonly />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Firmware</label>
                        <input v-model="form.firmware_version" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" readonly />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Last Connected</label>
                        <input
                            :value="form.last_connected_at ? new Date(form.last_connected_at).toLocaleString() : ''"
                            class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2"
                            readonly
                        />
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-medium">Notes</h2>
                <textarea v-model="form.notes" rows="4" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" />
                <div v-if="form.last_sync_error" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ form.last_sync_error }}
                </div>
            </section>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ isEditing ? 'Update Device' : 'Create Device' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
