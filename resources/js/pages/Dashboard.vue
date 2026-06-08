<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    stats: {
        devices: number;
        activeDevices: number;
        onlineDevices: number;
        punchesToday: number;
    };
}>();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-stone-500">Total Devices</p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.devices }}</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-stone-500">Active Devices</p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.activeDevices }}</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-stone-500">Online Devices</p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.onlineDevices }}</p>
            </div>
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-stone-500">Punches Today</p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.punchesToday }}</p>
            </div>
        </div>
    </AppLayout>
</template>
