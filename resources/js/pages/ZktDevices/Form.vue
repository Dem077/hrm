<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiAlert from '@/components/ui/UiAlert.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import type { BrandOption, ProtocolOption, ZktDevice } from '@/types/zkt';

const props = defineProps<{
    device: ZktDevice;
    protocols: ProtocolOption[];
    brands: BrandOption[];
}>();

const isEditing = computed(() => props.device.id !== null);
const probeMessage = ref<string | null>(null);
const probeError = ref<string | null>(null);
const probing = ref(false);

const form = useForm({
    name: props.device.name,
    brand: props.device.brand,
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
    <Head :title="isEditing ? 'Edit Machine' : 'Add Machine'" />

    <AppLayout>
        <PageHeader
            :title="isEditing ? 'Edit machine' : 'Add machine'"
            description="Enter network details, test the connection, then save the machine profile."
        >
            <template #actions>
                <UiButton variant="secondary" :disabled="probing || !form.ip_address" @click="probeDevice">
                    {{ probing ? 'Testing...' : 'Test & fetch info' }}
                </UiButton>
                <UiButton href="/zkt-devices" variant="ghost">Cancel</UiButton>
            </template>
        </PageHeader>

        <div class="mb-6 space-y-3">
            <UiAlert v-if="probeMessage" tone="success" :message="probeMessage" />
            <UiAlert v-if="probeError" tone="error" :message="probeError" />
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <UiCard title="Machine details" description="Brand and connection settings used to reach the attendance machine.">
                <div class="grid gap-5 md:grid-cols-2">
                    <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
                    <UiSelect v-model="form.brand" label="Brand" required :error="form.errors.brand">
                        <option v-for="option in brands" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </UiSelect>
                    <UiInput v-model="form.location" label="Location" />
                    <UiInput v-model="form.ip_address" label="IP address" required :error="form.errors.ip_address" />
                    <UiInput v-model="form.port" label="Port" type="number" required />
                    <UiSelect v-model="form.protocol" label="Protocol">
                        <option v-for="option in protocols" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </UiSelect>
                    <UiInput v-model="form.comm_password" label="Communication password" type="number" />
                </div>
            </UiCard>

            <UiCard title="Sync settings">
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 bg-white text-brand-600 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-surface dark:text-brand-500" />
                        Device is active
                    </label>
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300">
                        <input v-model="form.auto_sync" type="checkbox" class="rounded border-slate-300 bg-white text-brand-600 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-surface dark:text-brand-500" />
                        Enable automatic sync
                    </label>
                    <UiInput v-model="form.sync_interval_minutes" label="Sync interval (minutes)" type="number" />
                </div>
            </UiCard>

            <UiCard title="Machine metadata" description="Populated automatically after a successful connection test.">
                <div class="mb-5">
                    <UiBadge :label="connectionStatusLabel" :color="connectionStatusColor" />
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <UiInput v-model="form.serial_number" label="Serial number" readonly />
                    <UiInput v-model="form.model_name" label="Model" readonly />
                    <UiInput v-model="form.firmware_version" label="Firmware" readonly />
                    <UiInput
                        :model-value="form.last_connected_at ? formatDateTime(form.last_connected_at) : ''"
                        label="Last connected"
                        readonly
                    />
                </div>
            </UiCard>

            <UiCard title="Notes">
                <textarea
                    v-model="form.notes"
                    rows="4"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                />
                <div v-if="form.last_sync_error" class="mt-4">
                    <UiAlert tone="error" :message="form.last_sync_error" />
                </div>
            </UiCard>

            <div class="flex justify-end">
                <UiButton type="submit" variant="primary" :disabled="form.processing">
                    {{ isEditing ? 'Update machine' : 'Create machine' }}
                </UiButton>
            </div>
        </form>
    </AppLayout>
</template>
