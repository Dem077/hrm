<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiModal from '@/components/ui/UiModal.vue';

export type EmployeeImportPreviewRow = {
    line: number;
    staff_id: string;
    name: string;
    national_id: string;
    email: string;
    joined_date: string;
    gender: string;
    employment_type: string | null;
    duty_type: string;
    grade_id: number | null;
    grade_label: string | null;
    manager_staff_id: string | null;
    bank_name: string;
    account_name: string;
    account_no: string;
    action: 'create';
};

export type EmployeeImportPreview = {
    rows: EmployeeImportPreviewRow[];
    summary: {
        rows: number;
        create: number;
    };
};

const props = defineProps<{
    open: boolean;
    preview: EmployeeImportPreview | null;
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

    return `${s.rows} row(s) · ${s.create} employee(s) will be created`;
});

function cancel() {
    router.post(
        '/employees/import/cancel',
        {},
        {
            preserveScroll: true,
            onFinish: () => emit('close'),
        },
    );
}

function confirm() {
    confirmForm.post('/employees/import', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <UiModal
        :open="open"
        title="Review employee CSV import"
        :description="fileName ? `File: ${fileName}` : 'Verify the rows below before creating employees.'"
        max-width="2xl"
        @close="cancel"
    >
        <div v-if="preview" class="space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ summaryText }}</p>
            <p class="text-xs text-slate-500">
                Each imported employee gets a login account with a generated temporary password.
            </p>

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs dark:divide-slate-700">
                    <thead class="bg-slate-50 text-slate-600 dark:bg-slate-900/50 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 font-medium">#</th>
                            <th class="px-3 py-2 font-medium">Staff ID</th>
                            <th class="px-3 py-2 font-medium">Name</th>
                            <th class="px-3 py-2 font-medium">Email</th>
                            <th class="px-3 py-2 font-medium">Joined</th>
                            <th class="px-3 py-2 font-medium">Bank</th>
                            <th class="px-3 py-2 font-medium">Manager</th>
                            <th class="px-3 py-2 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="row in preview.rows"
                            :key="row.line"
                            class="text-slate-700 dark:text-slate-300"
                        >
                            <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ row.line }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono">{{ row.staff_id }}</td>
                            <td class="px-3 py-2">
                                <div>{{ row.name }}</div>
                                <div class="text-slate-500">{{ row.national_id }} · {{ row.gender }}</div>
                            </td>
                            <td class="px-3 py-2">{{ row.email }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ row.joined_date }}</td>
                            <td class="px-3 py-2">
                                <div>{{ row.bank_name }}</div>
                                <div class="text-slate-500">{{ row.account_no }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">{{ row.manager_staff_id || '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <UiBadge label="New" color="success" />
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
