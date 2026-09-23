<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ReportDefinition, ReportTemplate } from '@/types/reports';

defineProps<{
    templates: ReportTemplate[];
}>();

function modelType(template: ReportTemplate): string {
    const definition = (template.definition ?? {}) as ReportDefinition;
    return definition.model_type || '—';
}

function columnCount(template: ReportTemplate): number {
    const definition = (template.definition ?? {}) as ReportDefinition;
    return (definition.field_configs ?? []).filter((config) => config.field).length;
}

function destroyTemplate(id: number, name: string) {
    if (confirm(`Delete report template "${name}"?`)) {
        router.delete(`/reports/templates/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Manage Reports" />

    <AppLayout>
        <PageHeader
            title="Manage reports"
            description="Create report templates with columns, filters, and date ranges. Mark them global to share in the Reports nav."
        >
            <template #actions>
                <Link href="/reports/templates/create">
                    <UiButton variant="primary">New template</UiButton>
                </Link>
            </template>
        </PageHeader>

        <UiCard padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead
                        class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400"
                    >
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Name</th>
                            <th class="px-5 py-3.5 font-medium">Source</th>
                            <th class="px-5 py-3.5 font-medium">Columns</th>
                            <th class="px-5 py-3.5 font-medium">Visibility</th>
                            <th class="px-5 py-3.5 font-medium">Status</th>
                            <th class="px-5 py-3.5 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="template in templates" :key="template.id!">
                            <td class="px-5 py-4">
                                <p class="font-medium text-slate-900 dark:text-white">{{ template.name }}</p>
                                <p v-if="template.description" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ template.description }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ modelType(template) }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ columnCount(template) }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        template.is_global
                                            ? 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                    "
                                >
                                    {{ template.is_global ? 'Global' : 'Private' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        template.is_active
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                    "
                                >
                                    {{ template.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <Link :href="`/reports/${template.id}`">
                                        <UiButton size="sm" variant="ghost">Open</UiButton>
                                    </Link>
                                    <Link :href="`/reports/templates/${template.id}/edit`">
                                        <UiButton size="sm" variant="ghost">Edit</UiButton>
                                    </Link>
                                    <a :href="`/reports/${template.id}/download`">
                                        <UiButton size="sm" variant="secondary">CSV</UiButton>
                                    </a>
                                    <UiButton
                                        size="sm"
                                        variant="danger"
                                        @click="destroyTemplate(template.id!, template.name)"
                                    >
                                        Delete
                                    </UiButton>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="templates.length === 0">
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                No report templates yet.
                                <Link href="/reports/templates/create" class="text-brand-700 hover:underline dark:text-brand-300">
                                    Create one
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>
    </AppLayout>
</template>
