<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiModal from '@/components/ui/UiModal.vue';

export type ImportPreviewRow = {
    line: number;
    group_code: string;
    group_name: string;
    node_name: string;
    node_code: string;
    parent_node_name: string;
    level_number: number;
    reference_title: string;
    grade: string;
    grade_title: string;
    node_action: 'none' | 'create' | 'update';
    level_action: 'create' | 'update';
    grade_action: 'create' | 'update';
};

export type ImportPreview = {
    rows: ImportPreviewRow[];
    summary: {
        rows: number;
        create_nodes: number;
        update_nodes: number;
        create_levels: number;
        update_levels: number;
        create_grades: number;
        update_grades: number;
    };
};

const props = defineProps<{
    open: boolean;
    preview: ImportPreview | null;
    fileName?: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const confirmForm = useForm({});

const summaryText = computed(() => {
    if (!props.preview) {
        return '';
    }

    const s = props.preview.summary;

    return `${s.rows} row(s) · nodes ${s.create_nodes} new / ${s.update_nodes} update · levels ${s.create_levels} new / ${s.update_levels} update · grades ${s.create_grades} new / ${s.update_grades} update`;
});

function actionColor(action: string): string {
    if (action === 'create') {
        return 'success';
    }

    if (action === 'update') {
        return 'info';
    }

    return 'gray';
}

function actionLabel(action: string): string {
    if (action === 'create') {
        return 'New';
    }

    if (action === 'update') {
        return 'Update';
    }

    return '—';
}

function cancel() {
    router.post(
        '/company-structure/import/cancel',
        {},
        {
            preserveScroll: true,
            onFinish: () => emit('close'),
        },
    );
}

function confirm() {
    confirmForm.post('/company-structure/import', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <UiModal
        :open="open"
        title="Review CSV import"
        :description="fileName ? `File: ${fileName}` : 'Verify the rows below before writing to the database.'"
        max-width="2xl"
        @close="cancel"
    >
        <div v-if="preview" class="space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ summaryText }}</p>

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs dark:divide-slate-700">
                    <thead class="bg-slate-50 text-slate-600 dark:bg-slate-900/50 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">#</th>
                            <th class="px-3 py-2 font-medium">Group</th>
                            <th class="px-3 py-2 font-medium">Node</th>
                            <th class="px-3 py-2 font-medium">Parent</th>
                            <th class="px-3 py-2 font-medium">Level</th>
                            <th class="px-3 py-2 font-medium">Grade</th>
                            <th class="px-3 py-2 font-medium">Node</th>
                            <th class="px-3 py-2 font-medium">Level</th>
                            <th class="px-3 py-2 font-medium">Grade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="row in preview.rows"
                            :key="row.line"
                            class="text-slate-700 dark:text-slate-300"
                        >
                            <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ row.line }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ row.group_name }}</td>
                            <td class="px-3 py-2">
                                <div>{{ row.node_name || '—' }}</div>
                                <div v-if="row.node_code" class="text-slate-500">{{ row.node_code }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">{{ row.parent_node_name || '—' }}</td>
                            <td class="px-3 py-2">
                                <div>L{{ row.level_number }}</div>
                                <div class="text-slate-500">{{ row.reference_title }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <div>{{ row.grade }} · {{ row.grade_title }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <UiBadge :label="actionLabel(row.node_action)" :color="actionColor(row.node_action)" />
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <UiBadge :label="actionLabel(row.level_action)" :color="actionColor(row.level_action)" />
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <UiBadge :label="actionLabel(row.grade_action)" :color="actionColor(row.grade_action)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <template #footer>
            <UiButton type="button" variant="secondary" :disabled="confirmForm.processing" @click="cancel">
                Cancel
            </UiButton>
            <UiButton type="button" variant="primary" :disabled="confirmForm.processing || !preview" @click="confirm">
                {{ confirmForm.processing ? 'Importing…' : 'Confirm import' }}
            </UiButton>
        </template>
    </UiModal>
</template>
