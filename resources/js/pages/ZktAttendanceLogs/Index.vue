<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import type { AttendanceLog, Paginated } from '@/types/zkt';

const props = defineProps<{
    logs: Paginated<AttendanceLog>;
    devices: Array<{ id: number; name: string }>;
    filters: {
        device_id?: string | number | null;
        search?: string | null;
    };
}>();

const filters = reactive({
    device_id: props.filters.device_id ?? '',
    search: props.filters.search ?? '',
});

function applyFilters() {
    router.get('/zkt-attendance-logs', filters, {
        preserveState: true,
        replace: true,
    });
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="Punch Logs" />

    <AppLayout title="Punch Logs">
        <form class="mb-6 grid gap-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm md:grid-cols-3" @submit.prevent="applyFilters">
            <div>
                <label class="mb-1 block text-sm font-medium">Device</label>
                <select v-model="filters.device_id" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2">
                    <option value="">All devices</option>
                    <option v-for="device in devices" :key="device.id" :value="device.id">
                        {{ device.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Search</label>
                <input v-model="filters.search" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2" placeholder="User ID or device name" />
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                    Filter
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50 text-left text-stone-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Device</th>
                        <th class="px-4 py-3 font-medium">User ID</th>
                        <th class="px-4 py-3 font-medium">State</th>
                        <th class="px-4 py-3 font-medium">Punched At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    <tr v-for="log in logs.data" :key="log.id">
                        <td class="px-4 py-3">
                            <Link :href="`/zkt-devices/${log.device.id}`" class="text-amber-700 hover:underline">
                                {{ log.device.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">{{ log.device_user_id }}</td>
                        <td class="px-4 py-3">{{ log.punch_state_label }}</td>
                        <td class="px-4 py-3">{{ formatDate(log.punched_at) }}</td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="4" class="px-4 py-10 text-center text-stone-500">No punch logs found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="logs.links.length > 3" class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in logs.links"
                :key="`${link.label}-${link.url}`"
                :href="link.url ?? '#'"
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="[
                    link.active ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-stone-300 text-stone-600',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
