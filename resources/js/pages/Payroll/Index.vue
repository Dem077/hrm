<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiDateInput from '@/components/ui/UiDateInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';

type Run = {
    id: number;
    reference_no: string;
    period_label: string;
    period_from: string;
    period_to: string;
    period_source: string;
    status: 'draft' | 'processed' | 'finalised';
    status_label: string;
    created_by: string | null;
    processed_by: string | null;
    finalised_by: string | null;
    created_at: string | null;
    processed_at: string | null;
    finalised_at: string | null;
};

defineProps<{
    runs: Run[];
    periodPresentation: {
        current: { from: string; to: string; label: string };
    };
}>();

const { can } = usePermissions();
const page = usePage<{ errors: Record<string, string> }>();
const showStartModal = ref(false);

const startForm = reactive({
    period_source: 'global',
    from: '',
    to: '',
});

function statusColor(status: Run['status']): string {
    if (status === 'draft') return 'warning';
    if (status === 'processed') return 'info';
    return 'success';
}

function openRun(runId: number): void {
    router.visit(`/payroll/${runId}`);
}

function deleteRun(run: Run): void {
    if (!confirm(`Delete draft payroll ${run.reference_no}? This cannot be undone.`)) {
        return;
    }

    router.delete(`/payroll/${run.id}`, { preserveScroll: true });
}

function createRun(): void {
    router.post('/payroll', startForm, {
        onSuccess: () => {
            showStartModal.value = false;
            startForm.period_source = 'global';
            startForm.from = '';
            startForm.to = '';
        },
    });
}
</script>

<template>
    <Head title="Payroll" />

    <AppLayout>
        <PageHeader title="Payroll" description="View and manage payroll runs. Open a run to process, adjust, or export.">
            <template #actions>
                <UiButton v-if="can('payroll.create')" variant="primary" @click="showStartModal = true">
                    Start New Payroll
                </UiButton>
            </template>
        </PageHeader>

        <UiCard title="Payroll runs" description="Select a payroll run to open its details.">
            <p v-if="runs.length === 0" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                No payroll runs yet. Start a new payroll to create a draft.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-3 py-2">Reference</th>
                            <th class="px-3 py-2">Period</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Created by</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="run in runs"
                            :key="run.id"
                            class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-900/30"
                            @click="openRun(run.id)"
                        >
                            <td class="px-3 py-2 font-medium">{{ run.reference_no }}</td>
                            <td class="px-3 py-2">{{ run.period_label }}</td>
                            <td class="px-3 py-2">
                                <UiBadge :label="run.status_label" :color="statusColor(run.status)" />
                            </td>
                            <td class="px-3 py-2">{{ run.created_by ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <UiButton size="sm" variant="ghost" @click.stop="openRun(run.id)">Open</UiButton>
                                    <UiButton
                                        v-if="can('payroll.delete') && run.status === 'draft'"
                                        size="sm"
                                        variant="danger"
                                        @click.stop="deleteRun(run)"
                                    >
                                        Delete
                                    </UiButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <UiModal
            :open="showStartModal"
            title="Start New Payroll"
            description="Choose global payroll period or enter a custom date range."
            max-width="md"
            @close="showStartModal = false"
        >
            <div class="space-y-4">
                <UiSelect v-model="startForm.period_source" label="Period source">
                    <option value="global">Global payroll period ({{ periodPresentation.current.label }})</option>
                    <option value="custom">Custom date range</option>
                </UiSelect>

                <div v-if="startForm.period_source === 'custom'" class="grid gap-3 md:grid-cols-2">
                    <UiDateInput v-model="startForm.from" label="From" />
                    <UiDateInput v-model="startForm.to" label="To" />
                </div>

                <p v-if="page.props.errors.period" class="text-xs text-red-600 dark:text-red-400">
                    {{ page.props.errors.period }}
                </p>
            </div>
            <template #footer>
                <UiButton variant="ghost" @click="showStartModal = false">Cancel</UiButton>
                <UiButton variant="primary" @click="createRun">Create Draft</UiButton>
            </template>
        </UiModal>
    </AppLayout>
</template>
