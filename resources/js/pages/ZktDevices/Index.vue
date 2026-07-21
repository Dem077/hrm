<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import type { ZktDevice } from '@/types/zkt';

defineProps<{
    devices: ZktDevice[];
}>();

const { can } = usePermissions();

function syncAll() {
    router.post('/zkt-devices/sync-all');
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

function testDevice(id: number) {
    router.post(`/zkt-devices/${id}/test`, {}, { preserveScroll: true });
}

function iconTone(color?: string): string {
    const tones: Record<string, string> = {
        success: 'bg-emerald-500/15 text-emerald-600 ring-emerald-500/20 dark:text-emerald-400',
        danger: 'bg-red-500/15 text-red-600 ring-red-500/20 dark:text-red-400',
        warning: 'bg-amber-500/15 text-amber-600 ring-amber-500/20 dark:text-amber-400',
        gray: 'bg-slate-500/15 text-slate-600 ring-slate-500/20 dark:text-slate-400',
    };

    return tones[color ?? 'gray'] ?? tones.gray;
}
</script>

<template>
    <Head title="Attendance Machines" />

    <AppLayout>
        <PageHeader
            title="Attendance Machines"
            description="Manage biometric punch machines, test connectivity, and sync attendance."
        >
            <template #actions>
                <UiButton v-if="can('zkt-devices.sync-all')" variant="secondary" @click="syncAll">Sync all active</UiButton>
                <UiButton v-if="can('zkt-devices.create')" href="/zkt-devices/create" variant="primary">Add machine</UiButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="devices.length === 0"
            title="No machines yet"
            description="Add your first attendance machine to start syncing punches."
        >
            <template #icon>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4" />
                </svg>
            </template>
            <template v-if="can('zkt-devices.create')" #action>
                <UiButton href="/zkt-devices/create" variant="primary">Add machine</UiButton>
            </template>
        </EmptyState>

        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            <article
                v-for="device in devices"
                :key="device.id ?? device.name"
                class="group flex flex-col rounded-xl border border-slate-200 bg-surface p-3 shadow-sm transition hover:border-brand-500/30 hover:shadow-md dark:border-slate-800 dark:hover:border-brand-500/20"
            >
                <div class="flex items-start gap-2.5">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset"
                        :class="iconTone(device.connection_status_color)"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                        <Link
                            :href="`/zkt-devices/${device.id}`"
                            class="block truncate text-sm font-semibold leading-tight text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                        >
                            {{ device.name }}
                        </Link>
                        <p class="mt-0.5 truncate text-xs text-slate-500">
                            {{ device.brand_label ?? device.brand }} · {{ device.machine_type_short_label ?? device.machine_type_label ?? 'Attendance' }}
                        </p>
                    </div>
                </div>

                <div class="mt-2.5 space-y-1.5 text-xs">
                    <p class="truncate font-mono text-slate-700 dark:text-slate-300">
                        <template v-if="device.connection_mode === 'adms_push'">
                            ADMS · {{ device.serial_number ?? 'No SN' }}
                        </template>
                        <template v-else>
                            {{ device.ip_address }}:{{ device.port }}
                        </template>
                    </p>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex flex-wrap gap-1">
                            <UiBadge
                                :label="device.connection_status_label ?? device.connection_status"
                                :color="device.connection_status_color"
                            />
                            <UiBadge
                                :label="device.connection_mode === 'adms_push' ? 'ADMS' : 'TCP'"
                                :color="device.connection_mode === 'adms_push' ? 'info' : 'gray'"
                            />
                            <UiBadge
                                :label="device.machine_type_short_label ?? device.machine_type_label ?? 'Attendance'"
                                :color="device.machine_type_color ?? 'info'"
                            />
                        </div>
                        <span
                            class="truncate text-slate-500"
                            :title="formatDateTime(device.connection_mode === 'adms_push' ? device.last_adms_seen_at : device.last_synced_at)"
                        >
                            {{ formatDateTime(device.connection_mode === 'adms_push' ? device.last_adms_seen_at : device.last_synced_at) }}
                        </span>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-1 border-t border-slate-100 pt-2.5 dark:border-slate-800">
                    <button
                        v-if="can('zkt-devices.test') && device.connection_mode !== 'adms_push'"
                        type="button"
                        title="Test connection"
                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-surface-muted hover:text-slate-900 dark:hover:text-white"
                        @click="testDevice(device.id!)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </button>
                    <button
                        v-if="can('zkt-devices.read-time') && device.connection_mode !== 'adms_push'"
                        type="button"
                        title="Read device clock"
                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-surface-muted hover:text-slate-900 dark:hover:text-white"
                        @click="readDeviceTime(device.id!)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                    <button
                        v-if="can('zkt-devices.sync-time') && device.connection_mode !== 'adms_push'"
                        type="button"
                        title="Sync device clock"
                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-surface-muted hover:text-slate-900 dark:hover:text-white"
                        @click="syncDeviceTime(device.id!)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                    <button
                        v-if="can('zkt-devices.sync') && device.connection_mode !== 'adms_push'"
                        type="button"
                        title="Sync attendance"
                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-surface-muted hover:text-slate-900 dark:hover:text-white"
                        @click="syncDevice(device.id!)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                    <Link
                        v-if="can('zkt-devices.update')"
                        :href="`/zkt-devices/${device.id}/edit`"
                        title="Edit machine"
                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-surface-muted hover:text-slate-900 dark:hover:text-white"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </Link>
                    <Link
                        :href="`/zkt-devices/${device.id}`"
                        title="View details"
                        class="ml-auto rounded-lg p-1.5 text-slate-500 transition hover:bg-surface-muted hover:text-brand-600 dark:hover:text-brand-400"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
