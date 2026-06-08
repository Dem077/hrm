<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Department, SelectOption } from '@/types/hrm';

const props = defineProps<{
    department: Department;
    heads: SelectOption[];
}>();

const isEditing = computed(() => props.department.id !== null);

const form = useForm({
    name: props.department.name,
    code: props.department.code ?? '',
    description: props.department.description ?? '',
    head_employee_id: props.department.head_employee_id ?? '',
    is_active: props.department.is_active,
    sort_order: props.department.sort_order,
});

function submit() {
    if (isEditing.value) {
        form.put(`/departments/${props.department.id}`);
        return;
    }

    form.post('/departments');
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Department' : 'Add Department'" />

    <AppLayout>
        <PageHeader
            :title="isEditing ? 'Edit department' : 'Add department'"
            description="Simple department setup with one assigned head for approvals."
        >
            <template #actions>
                <UiButton href="/departments" variant="ghost">Cancel</UiButton>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <UiCard title="Department details">
                <div class="grid gap-5 md:grid-cols-2">
                    <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
                    <UiInput v-model="form.code" label="Code" hint="Optional short code" :error="form.errors.code" />
                    <UiInput v-model="form.sort_order" label="Sort order" type="number" :error="form.errors.sort_order" />
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 bg-white text-brand-600 dark:border-slate-600 dark:bg-surface dark:text-brand-500" />
                        Department is active
                    </label>
                </div>
                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                    />
                </div>
            </UiCard>

            <UiCard title="Approval routing" description="Assign one department head. Employees still use their direct manager for day-to-day approvals.">
                <UiSelect v-model="form.head_employee_id" label="Department head" :error="form.errors.head_employee_id">
                    <option value="">No head assigned</option>
                    <option v-for="head in heads" :key="head.id" :value="head.id">
                        {{ head.label }}
                    </option>
                </UiSelect>
            </UiCard>

            <div class="flex justify-end">
                <UiButton type="submit" variant="primary" :disabled="form.processing">
                    {{ isEditing ? 'Update department' : 'Create department' }}
                </UiButton>
            </div>
        </form>
    </AppLayout>
</template>
