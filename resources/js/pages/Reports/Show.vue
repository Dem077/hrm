<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ReportDefinition, ReportTemplate } from '@/types/reports';

const props = defineProps<{
    template: ReportTemplate;
    modelLabel: string;
}>();

const { can } = usePermissions();

const definition = (props.template.definition ?? {}) as ReportDefinition;
const columns = (definition.field_configs ?? []).filter((config) => config.field);
</script>

<template>
    <Head :title="template.name" />

    <AppLayout>
        <PageHeader
            :title="template.name"
            :description="template.description || `Export ${modelLabel} data as configured in this template.`"
        >
            <template #actions>
                <a :href="`/reports/${template.id}/download`">
                    <UiButton variant="primary">Download CSV</UiButton>
                </a>
                <Link v-if="can('reports.manage')" :href="`/reports/templates/${template.id}/edit`">
                    <UiButton variant="secondary">Edit template</UiButton>
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <UiCard class="lg:col-span-1">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Details</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Source</dt>
                        <dd class="font-medium text-slate-900 dark:text-white">{{ modelLabel }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Visibility</dt>
                        <dd class="font-medium text-slate-900 dark:text-white">
                            {{ template.is_global ? 'Global' : 'Private' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Period</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-white">
                            {{
                                definition.from_date && definition.to_date
                                    ? `${definition.from_date} → ${definition.to_date}`
                                    : 'All dates'
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Created by</dt>
                        <dd class="font-medium text-slate-900 dark:text-white">
                            {{ template.created_by?.name ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </UiCard>

            <UiCard class="lg:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Columns</h2>
                <ol v-if="columns.length" class="mt-3 space-y-2">
                    <li
                        v-for="(col, index) in columns"
                        :key="`${col.field}-${index}`"
                        class="flex items-start gap-3 rounded-xl border border-slate-100 px-3 py-2.5 text-sm dark:border-slate-800"
                    >
                        <span
                            class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ index + 1 }}
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900 dark:text-white">
                                {{ col.heading || col.field }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ col.field }}
                                <template v-if="col.filter_type"> · filter: {{ col.filter_type }}</template>
                            </p>
                        </div>
                    </li>
                </ol>
                <p v-else class="mt-3 text-sm text-slate-500">No columns configured yet.</p>
            </UiCard>
        </div>
    </AppLayout>
</template>
