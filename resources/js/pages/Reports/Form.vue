<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiDateInput from '@/components/ui/UiDateInput.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSearchableSelect from '@/components/ui/UiSearchableSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ReportCatalog, ReportFieldConfig, ReportTemplate } from '@/types/reports';

const props = defineProps<{
    template: ReportTemplate | null;
    catalog: ReportCatalog;
    emptyTemplate: ReportTemplate;
}>();

const isEdit = !!props.template?.id;

function emptyColumn(): ReportFieldConfig {
    return {
        field: '',
        heading: '',
        filter_type: '',
        filter_value: '',
        filter_value_from: '',
        filter_value_to: '',
        filter_values: [],
    };
}

function definitionFrom(template: ReportTemplate | null) {
    const definition = (template?.definition ?? props.emptyTemplate.definition ?? {}) as {
        model_type?: string;
        field_configs?: Partial<ReportFieldConfig>[];
        from_date?: string | null;
        to_date?: string | null;
    };

    return {
        model_type: definition.model_type ?? props.catalog.models[0]?.value ?? '',
        field_configs: (definition.field_configs ?? []).map((config) => ({
            field: config.field ?? '',
            heading: config.heading ?? '',
            filter_type: config.filter_type ?? '',
            filter_value: config.filter_value ?? '',
            filter_value_from: config.filter_value_from ?? '',
            filter_value_to: config.filter_value_to ?? '',
            filter_values: config.filter_values ?? [],
        })),
        from_date: definition.from_date ?? '',
        to_date: definition.to_date ?? '',
    };
}

const initial = definitionFrom(props.template);

const form = useForm({
    name: props.template?.name ?? '',
    code: props.template?.code ?? '',
    description: props.template?.description ?? '',
    is_global: props.template?.is_global ?? false,
    is_active: props.template?.is_active ?? true,
    sort_order: props.template?.sort_order ?? 0,
    model_type: initial.model_type,
    field_configs: initial.field_configs.length ? initial.field_configs : [],
    from_date: initial.from_date,
    to_date: initial.to_date,
});

const currentModel = computed(
    () => props.catalog.models.find((model) => model.value === form.model_type) ?? null,
);

const fields = computed(() => currentModel.value?.fields ?? []);

function fieldMeta(fieldName: string) {
    return fields.value.find((field) => field.value === fieldName);
}

const usedFields = computed(() => form.field_configs.map((col) => col.field).filter(Boolean));

const unusedFields = computed(() => fields.value.filter((field) => !usedFields.value.includes(field.value)));

const suggestedFields = computed(() => unusedFields.value.slice(0, 8));

function fieldOptionsForColumn(col: ReportFieldConfig) {
    const options = unusedFields.value.map((field) => ({
        value: field.value,
        label: field.label,
    }));

    if (col.field && !options.some((option) => option.value === col.field)) {
        const current = fieldMeta(col.field);
        if (current) {
            options.unshift({ value: current.value, label: current.label });
        }
    }

    return options;
}

function columnHeading(col: ReportFieldConfig) {
    return (col.heading || '').trim() || fieldMeta(col.field)?.label || 'New column';
}

function columnStatus(col: ReportFieldConfig) {
    if (!col.field) {
        return { label: 'Incomplete', class: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' };
    }
    if (col.filter_type) {
        return { label: 'Filtered', class: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' };
    }
    return { label: 'Ready', class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' };
}

const readyColumns = computed(() => form.field_configs.filter((col) => col.field).length);

const filteredCount = computed(
    () => form.field_configs.filter((col) => col.field && col.filter_type).length,
);

const previewHeaders = computed(() =>
    form.field_configs.filter((col) => col.field).map((col) => columnHeading(col)),
);

function setModel(value: string) {
    if (value === form.model_type) {
        return;
    }
    if (form.field_configs.length && !confirm('Switching data source clears the selected columns. Continue?')) {
        return;
    }
    form.model_type = value;
    form.field_configs = [];
}

function addColumn(field = '') {
    const col = emptyColumn();
    if (field) {
        col.field = field;
    }
    form.field_configs.push(col);
}

function removeColumn(index: number) {
    form.field_configs.splice(index, 1);
}

function duplicateColumn(index: number) {
    const source = form.field_configs[index];
    const next = unusedFields.value[0];
    form.field_configs.splice(index + 1, 0, {
        ...emptyColumn(),
        ...source,
        field: next?.value || '',
        heading: '',
        filter_type: '',
        filter_value: '',
        filter_value_from: '',
        filter_value_to: '',
        filter_values: [],
    });
}

function moveColumn(index: number, direction: number) {
    const next = index + direction;
    if (next < 0 || next >= form.field_configs.length) {
        return;
    }
    const rows = form.field_configs;
    const [row] = rows.splice(index, 1);
    rows.splice(next, 0, row);
}

function onFieldChange(col: ReportFieldConfig) {
    col.heading = '';
    col.filter_type = '';
    col.filter_value = '';
    col.filter_value_from = '';
    col.filter_value_to = '';
    col.filter_values = [];
}

function filterTypeOptions(col: ReportFieldConfig) {
    const meta = fieldMeta(col.field);
    if (!meta || meta.is_relation) {
        return [];
    }
    return (meta.filter_types || []).map((type) => ({
        value: type.value,
        label: type.label,
    }));
}

function statusOptions(col: ReportFieldConfig) {
    return (fieldMeta(col.field)?.options || []).map((option) => ({
        value: option.value,
        label: option.label,
    }));
}

function fieldError(key: string) {
    return form.errors[key] || '';
}

function columnError(index: number, field: string) {
    return form.errors[`field_configs.${index}.${field}`] || '';
}

function submit() {
    if (isEdit && props.template?.id) {
        form.put(`/reports/templates/${props.template.id}`);
        return;
    }
    form.post('/reports/templates');
}
</script>

<template>
    <Head :title="isEdit ? `Edit ${template?.name}` : 'New report template'" />

    <AppLayout>
        <PageHeader
            :title="isEdit ? `Edit ${template?.name}` : 'Create report template'"
            description="Choose a data source, add columns in CSV order, then optionally filter and date-range the export."
        >
            <template #actions>
                <Link href="/reports/manage">
                    <UiButton variant="ghost">Back</UiButton>
                </Link>
            </template>
        </PageHeader>

        <form class="space-y-4 pb-28" @submit.prevent="submit">
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="space-y-4 xl:col-span-8">
                    <UiCard>
                        <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Template</h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <UiInput v-model="form.name" label="Name" required :error="fieldError('name')" class="md:col-span-2" />
                            <UiInput v-model="form.code" label="Code" hint="Optional short code" :error="fieldError('code')" />
                            <UiInput v-model="form.sort_order" label="Sort order" type="number" :error="fieldError('sort_order')" />
                            <UiInput
                                v-model="form.description"
                                label="Description"
                                :error="fieldError('description')"
                                class="md:col-span-2"
                            />
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                                <input
                                    v-model="form.is_global"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-600 dark:border-slate-600"
                                />
                                <span>
                                    <span class="block font-medium text-slate-800 dark:text-slate-200">Global</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        Visible to everyone with reports permission
                                    </span>
                                </span>
                            </label>
                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-600 dark:border-slate-600"
                                />
                                Active
                            </label>
                        </div>
                    </UiCard>

                    <UiCard>
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Data source</h2>
                            <span
                                v-if="currentModel"
                                class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950/60 dark:text-brand-300"
                            >
                                {{ currentModel.label }}
                            </span>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="model in catalog.models"
                                :key="model.value"
                                type="button"
                                class="rounded-xl border px-3 py-3 text-left transition"
                                :class="
                                    form.model_type === model.value
                                        ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                        : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-surface-muted/40'
                                "
                                @click="setModel(model.value)"
                            >
                                <p class="text-sm font-semibold">{{ model.label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ model.fields.length }} fields
                                </p>
                            </button>
                        </div>
                        <p v-if="fieldError('model_type')" class="mt-2 text-xs text-red-600">{{ fieldError('model_type') }}</p>
                    </UiCard>

                    <UiCard padding="none">
                        <div class="border-b border-slate-100 bg-gradient-to-r from-brand-50/80 to-transparent px-5 py-4 dark:border-slate-800 dark:from-brand-950/30">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Columns</h2>
                                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                        Add fields in the order they should appear in the CSV.
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ readyColumns }}/{{ form.field_configs.length || 0 }} ready
                                    </span>
                                    <UiButton
                                        type="button"
                                        size="sm"
                                        :disabled="!unusedFields.length && form.field_configs.length > 0"
                                        @click="addColumn()"
                                    >
                                        Add column
                                    </UiButton>
                                </div>
                            </div>
                        </div>

                        <div v-if="!form.field_configs.length" class="px-5 py-12 text-center">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">No columns yet</p>
                            <p class="mt-1 text-sm text-slate-500">Pick a field below, or add a blank column and search for it.</p>
                            <div v-if="suggestedFields.length" class="mt-4 flex flex-wrap justify-center gap-2">
                                <button
                                    v-for="field in suggestedFields"
                                    :key="field.value"
                                    type="button"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:border-brand-300 hover:text-brand-800 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-200"
                                    @click="addColumn(field.value)"
                                >
                                    {{ field.label }}
                                </button>
                            </div>
                            <UiButton type="button" class="mt-4" @click="addColumn()">Add first column</UiButton>
                        </div>

                        <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                            <article v-for="(col, index) in form.field_configs" :key="index" class="px-5 py-5">
                                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-semibold text-white">
                                            {{ index + 1 }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                                {{ columnHeading(col) }}
                                            </p>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ fieldMeta(col.field)?.label || 'Select a field' }}
                                                <template v-if="fieldMeta(col.field)?.is_relation"> · related</template>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="columnStatus(col).class">
                                            {{ columnStatus(col).label }}
                                        </span>
                                        <button
                                            type="button"
                                            class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 disabled:opacity-40 dark:hover:bg-slate-800"
                                            :disabled="index === 0"
                                            @click="moveColumn(index, -1)"
                                        >
                                            Up
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 disabled:opacity-40 dark:hover:bg-slate-800"
                                            :disabled="index === form.field_configs.length - 1"
                                            @click="moveColumn(index, 1)"
                                        >
                                            Down
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 disabled:opacity-40 dark:hover:bg-slate-800"
                                            :disabled="!unusedFields.length"
                                            @click="duplicateColumn(index)"
                                        >
                                            Duplicate
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                            @click="removeColumn(index)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                <div class="grid gap-3 lg:grid-cols-12">
                                    <div class="lg:col-span-5">
                                        <UiSearchableSelect
                                            :id="`report-field-${index}`"
                                            v-model="col.field"
                                            label="Field"
                                            :options="fieldOptionsForColumn(col)"
                                            placeholder="Search fields…"
                                            :error="columnError(index, 'field')"
                                            @update:model-value="onFieldChange(col)"
                                        />
                                    </div>
                                    <div class="lg:col-span-4">
                                        <UiInput
                                            v-model="col.heading"
                                            label="CSV heading"
                                            :placeholder="fieldMeta(col.field)?.label || 'Optional custom name'"
                                        />
                                    </div>
                                    <div class="lg:col-span-3">
                                        <UiSearchableSelect
                                            v-if="col.field && !fieldMeta(col.field)?.is_relation"
                                            :id="`report-filter-${index}`"
                                            v-model="col.filter_type"
                                            label="Filter"
                                            :options="filterTypeOptions(col)"
                                            empty-label="No filter"
                                            placeholder="No filter"
                                            :error="columnError(index, 'filter_type')"
                                        />
                                        <p v-else-if="fieldMeta(col.field)?.is_relation" class="pt-7 text-[11px] text-slate-400">
                                            Related fields cannot be filtered.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="col.filter_type && !fieldMeta(col.field)?.is_relation"
                                    class="mt-3 grid gap-3 sm:grid-cols-2"
                                >
                                    <template v-if="col.filter_type === 'between'">
                                        <UiInput v-model="col.filter_value_from" label="From" />
                                        <UiInput v-model="col.filter_value_to" label="To" />
                                    </template>
                                    <template v-else-if="col.filter_type === 'in' && fieldMeta(col.field)?.input === 'status'">
                                        <div class="sm:col-span-2">
                                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                                Values
                                            </label>
                                            <select
                                                v-model="col.filter_values"
                                                multiple
                                                class="min-h-28 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm dark:border-slate-700 dark:bg-surface-elevated"
                                            >
                                                <option
                                                    v-for="option in statusOptions(col)"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </template>
                                    <template v-else-if="col.filter_type === 'in'">
                                        <div class="sm:col-span-2">
                                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                                Values
                                            </label>
                                            <input
                                                :value="(col.filter_values || []).join(', ')"
                                                type="text"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated"
                                                placeholder="Comma-separated values"
                                                @input="
                                                    col.filter_values = String(($event.target as HTMLInputElement).value)
                                                        .split(',')
                                                        .map((s) => s.trim())
                                                        .filter(Boolean)
                                                "
                                            />
                                        </div>
                                    </template>
                                    <template v-else-if="fieldMeta(col.field)?.input === 'status'">
                                        <UiSearchableSelect
                                            :id="`report-status-${index}`"
                                            v-model="col.filter_value"
                                            label="Value"
                                            :options="statusOptions(col)"
                                            placeholder="Select value…"
                                        />
                                    </template>
                                    <template v-else>
                                        <UiInput v-model="col.filter_value" label="Value" />
                                    </template>
                                </div>
                            </article>
                        </div>

                        <div v-if="form.field_configs.length" class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                            <button
                                type="button"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm font-medium text-slate-600 transition hover:border-brand-400 hover:bg-brand-50/50 hover:text-brand-800 dark:border-slate-600 dark:text-slate-300"
                                @click="addColumn()"
                            >
                                Add another column
                            </button>
                            <div v-if="suggestedFields.length" class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-for="field in suggestedFields"
                                    :key="field.value"
                                    type="button"
                                    class="rounded-full border border-slate-200 px-2.5 py-1 text-[11px] font-medium text-slate-600 hover:border-brand-300 hover:text-brand-800 dark:border-slate-700 dark:text-slate-300"
                                    @click="addColumn(field.value)"
                                >
                                    + {{ field.label }}
                                </button>
                            </div>
                            <p v-if="fieldError('field_configs')" class="mt-3 text-sm text-red-600">
                                {{ fieldError('field_configs') }}
                            </p>
                        </div>
                    </UiCard>

                    <UiCard>
                        <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-white">Date range</h2>
                        <p class="mb-3 text-xs text-slate-500">Optional. Applied to the record created date.</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <UiDateInput v-model="form.from_date" label="From" :error="fieldError('from_date')" />
                            <UiDateInput v-model="form.to_date" label="To" :error="fieldError('to_date')" />
                        </div>
                    </UiCard>
                </div>

                <aside class="xl:col-span-4">
                    <div class="sticky top-20 space-y-3">
                        <UiCard>
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Preview</h2>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Source</dt>
                                    <dd class="font-medium text-slate-900 dark:text-white">
                                        {{ currentModel?.label || '—' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Columns</dt>
                                    <dd class="font-medium text-slate-900 dark:text-white">{{ readyColumns }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Filters</dt>
                                    <dd class="font-medium text-slate-900 dark:text-white">{{ filteredCount }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Period</dt>
                                    <dd class="text-right font-medium text-slate-900 dark:text-white">
                                        {{
                                            form.from_date && form.to_date
                                                ? `${form.from_date} → ${form.to_date}`
                                                : 'All dates'
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </UiCard>

                        <UiCard>
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">CSV headers</h2>
                            <ol v-if="previewHeaders.length" class="mt-3 space-y-1.5">
                                <li
                                    v-for="(header, index) in previewHeaders"
                                    :key="`${header}-${index}`"
                                    class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
                                >
                                    <span
                                        class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-100 text-[11px] font-semibold text-slate-500 dark:bg-slate-800"
                                    >
                                        {{ index + 1 }}
                                    </span>
                                    <span class="truncate">{{ header }}</span>
                                </li>
                            </ol>
                            <p v-else class="mt-3 text-sm text-slate-500">Headers appear here as you add columns.</p>
                        </UiCard>
                    </div>
                </aside>
            </div>

            <div
                class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/90 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-surface/95 lg:left-[280px]"
            >
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <p class="hidden text-xs text-slate-500 sm:block dark:text-slate-400">
                        {{ readyColumns }} column{{ readyColumns === 1 ? '' : 's' }}
                        <template v-if="currentModel"> · {{ currentModel.label }}</template>
                    </p>
                    <div class="ml-auto flex items-center gap-2">
                        <Link href="/reports/manage">
                            <UiButton type="button" variant="ghost">Cancel</UiButton>
                        </Link>
                        <UiButton type="submit" variant="primary" :disabled="form.processing || !readyColumns">
                            {{ form.processing ? 'Saving…' : isEdit ? 'Save template' : 'Create template' }}
                        </UiButton>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
