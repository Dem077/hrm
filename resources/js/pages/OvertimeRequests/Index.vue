<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import EmptyState from '@/components/ui/EmptyState.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { OvertimeRequestItem } from '@/types/overtime';

const props = defineProps<{
    requests: OvertimeRequestItem[];
    filters: { filter: string };
    canCreate: boolean;
    canViewAll: boolean;
    hasEmployeeProfile: boolean;
    pendingApprovalCount: number;
    pendingHrApprovalCount: number;
}>();

const { can } = usePermissions();

type FilterTab = {
    value: string;
    label: string;
};

const filterTabs = computed<FilterTab[]>(() => {
    const tabs: FilterTab[] = [
        { value: 'mine', label: 'My requests' },
        { value: 'all', label: 'All' },
    ];

    if (can('overtime-requests.approve')) {
        tabs.push({
            value: 'pending-approval',
            label: props.pendingApprovalCount
                ? `Pending my approval (${props.pendingApprovalCount})`
                : 'Pending my approval',
        });
    }

    if (can('overtime-requests.approve-hr')) {
        tabs.push({
            value: 'pending-hr',
            label: props.pendingHrApprovalCount
                ? `Pending HR (${props.pendingHrApprovalCount})`
                : 'Pending HR',
        });
    }

    if (props.canViewAll) {
        tabs.push({ value: 'pending', label: 'All pending' });
    }

    return tabs;
});

function setFilter(filter: string) {
    if (filter === props.filters.filter) {
        return;
    }

    router.get('/overtime-requests', { filter }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Overtime" />

    <AppLayout>
        <PageHeader
            title="Overtime"
            description="Apply for overtime after regular hours. Requests use the same approval workflow as leave."
        >
            <template #actions>
                <UiButton v-if="canCreate && hasEmployeeProfile" href="/overtime-requests/create" variant="primary">
                    Apply for overtime
                </UiButton>
            </template>
        </PageHeader>

        <div class="mb-6 flex justify-center overflow-x-auto">
            <div class="inline-flex gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-surface-muted">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.value"
                    type="button"
                    class="whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition"
                    :class="
                        filters.filter === tab.value
                            ? 'bg-white text-brand-700 shadow-sm ring-1 ring-slate-200 dark:bg-surface-elevated dark:text-brand-400 dark:ring-slate-700'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                    "
                    @click="setFilter(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <EmptyState
            v-if="requests.length === 0"
            title="No overtime requests"
            description="Overtime applications you submit or need to approve will appear here."
        >
            <template v-if="canCreate && hasEmployeeProfile" #action>
                <UiButton href="/overtime-requests/create" variant="primary">Apply for overtime</UiButton>
            </template>
        </EmptyState>

        <UiCard v-else padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Record no.</th>
                            <th class="px-5 py-3.5 font-medium">Employee</th>
                            <th class="px-5 py-3.5 font-medium">Date</th>
                            <th class="px-5 py-3.5 font-medium">Hours</th>
                            <th class="px-5 py-3.5 font-medium">Approver</th>
                            <th class="px-5 py-3.5 font-medium">Status</th>
                            <th class="px-5 py-3.5 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="request in requests" :key="request.id" class="hover:bg-slate-50/60 dark:hover:bg-surface-elevated/60">
                            <td class="px-5 py-4 font-mono text-xs text-slate-700 dark:text-slate-300">{{ request.record_number }}</td>
                            <td class="px-5 py-4">
                                <p class="font-medium text-slate-900 dark:text-white">{{ request.employee?.name }}</p>
                                <p class="text-xs text-slate-500">{{ request.employee?.department?.name ?? '—' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDate(request.overtime_date) }}</td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ request.hours }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ request.approver_label ?? request.approver?.name ?? '—' }}</td>
                            <td class="px-5 py-4">
                                <UiBadge :label="request.status_label" :color="request.status_color" />
                            </td>
                            <td class="px-5 py-4 text-right">
                                <UiButton size="sm" :href="`/overtime-requests/${request.id}`" variant="ghost">View</UiButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>
    </AppLayout>
</template>
