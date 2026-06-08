<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import StatCard from '@/components/ui/StatCard.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
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

    <AppLayout
        title="Dashboard"
        description="Overview of your biometric devices and today's attendance activity."
    >
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Total Machines" :value="stats.devices" hint="Registered attendance machines" accent="slate" />
            <StatCard label="Active Machines" :value="stats.activeDevices" hint="Enabled for sync" accent="sky" />
            <StatCard label="Online Machines" :value="stats.onlineDevices" hint="Last connection successful" accent="emerald" />
            <StatCard label="Punches Today" :value="stats.punchesToday" hint="Synced attendance records" accent="amber" />
        </div>

        <div class="mt-8">
            <UiCard title="Quick actions" description="Common tasks to keep attendance data up to date.">
                <div class="flex flex-wrap gap-3">
                    <UiButton href="/zkt-devices/create" variant="primary">Add machine</UiButton>
                    <UiButton href="/zkt-devices" variant="secondary">Manage machines</UiButton>
                    <UiButton href="/zkt-attendance-logs" variant="secondary">View punch logs</UiButton>
                </div>
            </UiCard>
        </div>
    </AppLayout>
</template>
