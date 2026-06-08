<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
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
</script>

<template>
    <Head title="Punch Logs" />

    <AppLayout>
        <PageHeader
            title="Punch logs"
            description="Review synced attendance punches across all connected devices."
        />

        <div class="mb-6">
            <UiCard padding="sm">
            <form class="grid gap-4 md:grid-cols-[1fr_1fr_auto]" @submit.prevent="applyFilters">
                <UiSelect v-model="filters.device_id" label="Device">
                    <option value="">All devices</option>
                    <option v-for="device in devices" :key="device.id" :value="device.id">
                        {{ device.name }}
                    </option>
                </UiSelect>
                <UiInput v-model="filters.search" label="Search" placeholder="Emp no, name, or device" />
                <div class="flex items-end">
                    <UiButton type="submit" variant="primary">Apply filters</UiButton>
                </div>
            </form>
            </UiCard>
        </div>

        <UiCard padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Device</th>
                            <th class="px-5 py-3.5 font-medium">Emp No</th>
                            <th class="px-5 py-3.5 font-medium">Employee</th>
                            <th class="px-5 py-3.5 font-medium">State</th>
                            <th class="px-5 py-3.5 font-medium">Punched at</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50/60 dark:hover:bg-surface-elevated/60">
                            <td class="px-5 py-4">
                                <Link :href="`/zkt-devices/${log.device.id}`" class="font-medium text-brand-700 hover:text-brand-600 hover:underline dark:text-brand-400 dark:hover:text-brand-300">
                                    {{ log.device.name }}
                                </Link>
                            </td>
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
                        <tr v-if="logs.data.length === 0">
                            <td colspan="5" class="px-5 py-12 text-center text-slate-500">No punch logs found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <div v-if="logs.links.length > 3" class="mt-5 flex flex-wrap gap-2">
            <Link
                v-for="link in logs.links"
                :key="`${link.label}-${link.url}`"
                :href="link.url ?? '#'"
                class="rounded-lg border px-3 py-1.5 text-sm transition"
                :class="[
                    link.active
                        ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-600/40 dark:bg-brand-600/15 dark:text-brand-400'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400 dark:hover:bg-surface-muted dark:hover:text-slate-200',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
