<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiRichTextEditor from '@/components/ui/UiRichTextEditor.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { isRichTextEmpty, sanitizeRichText } from '@/lib/richText';

type DesignationRow = {
    id: number;
    grade: string;
    title: string;
    label: string;
    requirements: string | null;
    job_description: string | null;
    is_active: boolean;
    path_label: string;
    group_name: string | null;
    node_name: string | null;
    level_label: string | null;
    employee_count: number;
};

const props = defineProps<{
    designations: DesignationRow[];
    filters: { q: string };
    can_edit: boolean;
}>();

const searchText = ref(props.filters.q ?? '');
const selectedId = ref<number | null>(null);
const editOpen = ref(false);

const selected = computed(() => props.designations.find((row) => row.id === selectedId.value) ?? null);

const detailsForm = useForm({
    requirements: '',
    job_description: '',
});

watch(
    () => props.filters.q,
    (value) => {
        searchText.value = value ?? '';
    },
);

function applySearch(): void {
    router.get(
        '/company-structure/designations',
        { q: searchText.value || undefined },
        { preserveState: true, replace: true },
    );
}

function selectDesignation(row: DesignationRow): void {
    selectedId.value = row.id;
}

function openEdit(): void {
    if (!selected.value || !props.can_edit) {
        return;
    }

    detailsForm.requirements = selected.value.requirements ?? '';
    detailsForm.job_description = selected.value.job_description ?? '';
    detailsForm.clearErrors();
    editOpen.value = true;
}

function closeEdit(): void {
    editOpen.value = false;
    detailsForm.reset();
    detailsForm.clearErrors();
}

function submitDetails(): void {
    if (!selected.value) {
        return;
    }

    detailsForm.put(`/company-structure/grades/${selected.value.id}/details`, {
        preserveScroll: true,
        onSuccess: () => {
            closeEdit();
        },
    });
}

function hasContent(value: string | null | undefined): boolean {
    return !isRichTextEmpty(value);
}

function renderedHtml(value: string | null | undefined): string {
    return sanitizeRichText(value);
}
</script>

<template>
    <Head title="Designations" />

    <AppLayout>
        <PageHeader
            title="Designations"
            description="Browse all designations across the company structure. Review and update requirements and job descriptions."
        >
            <template #actions>
                <UiButton href="/company-structure" variant="ghost">Company structure</UiButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap items-end gap-3">
            <UiInput
                v-model="searchText"
                label="Search"
                placeholder="Code, title, department, or path"
                class="min-w-[16rem] flex-1"
            />
            <UiButton variant="secondary" @click="applySearch">Search</UiButton>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)]">
            <UiCard
                title="All designations"
                :description="`${designations.length} designation${designations.length === 1 ? '' : 's'}`"
            >
                <div class="space-y-2">
                    <button
                        v-for="row in designations"
                        :key="row.id"
                        type="button"
                        class="w-full rounded-xl border px-4 py-3 text-left transition"
                        :class="
                            selectedId === row.id
                                ? 'border-brand-500 bg-brand-50/70 dark:border-brand-400 dark:bg-brand-950/30'
                                : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:border-slate-600 dark:hover:bg-surface-elevated'
                        "
                        @click="selectDesignation(row)"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ row.title }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Grade {{ row.grade }}
                                    <span v-if="row.level_label"> · {{ row.level_label }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <UiBadge
                                    :label="row.is_active ? 'Active' : 'Inactive'"
                                    :color="row.is_active ? 'success' : 'warning'"
                                />
                                <span class="text-xs text-slate-500">{{ row.employee_count }} staff</span>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">{{ row.path_label }}</p>
                        <div class="mt-2 flex flex-wrap gap-3 text-[11px] uppercase tracking-wide text-slate-400">
                            <span>Requirements: {{ hasContent(row.requirements) ? 'Set' : 'Missing' }}</span>
                            <span>Job description: {{ hasContent(row.job_description) ? 'Set' : 'Optional' }}</span>
                        </div>
                    </button>

                    <p
                        v-if="designations.length === 0"
                        class="py-10 text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        No designations match your search.
                    </p>
                </div>
            </UiCard>

            <UiCard title="Details" description="Select a designation to review its requirements and job description.">
                <div v-if="selected" class="space-y-5">
                    <div>
                        <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ selected.title }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ selected.label }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ selected.path_label }}</p>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requirements</h3>
                        <div
                            v-if="hasContent(selected.requirements)"
                            class="rich-text-content mt-2 text-sm text-slate-700 dark:text-slate-200"
                            v-html="renderedHtml(selected.requirements)"
                        />
                        <p v-else class="mt-2 text-sm text-slate-500">No requirements recorded yet.</p>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Job description</h3>
                        <div
                            v-if="hasContent(selected.job_description)"
                            class="rich-text-content mt-2 text-sm text-slate-700 dark:text-slate-200"
                            v-html="renderedHtml(selected.job_description)"
                        />
                        <p v-else class="mt-2 text-sm text-slate-500">No job description yet.</p>
                    </div>

                    <div v-if="can_edit" class="flex justify-end">
                        <UiButton variant="primary" @click="openEdit">Edit details</UiButton>
                    </div>
                </div>

                <p v-else class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                    Choose a designation from the list.
                </p>
            </UiCard>
        </div>

        <UiModal
            :open="editOpen"
            title="Edit designation details"
            :description="selected ? selected.label : ''"
            max-width="lg"
            @close="closeEdit"
        >
            <form id="designation-details-form" class="space-y-4" @submit.prevent="submitDetails">
                <UiRichTextEditor
                    v-model="detailsForm.requirements"
                    label="Requirements"
                    required
                    placeholder="Education, experience, skills, and other requirements."
                    :error="detailsForm.errors.requirements"
                />
                <UiRichTextEditor
                    v-model="detailsForm.job_description"
                    label="Job description"
                    hint="Optional"
                    placeholder="Optional summary of duties and responsibilities."
                    :error="detailsForm.errors.job_description"
                />
            </form>
            <template #footer>
                <UiButton type="button" variant="ghost" :disabled="detailsForm.processing" @click="closeEdit">
                    Cancel
                </UiButton>
                <UiButton
                    type="submit"
                    form="designation-details-form"
                    variant="primary"
                    :disabled="detailsForm.processing"
                >
                    {{ detailsForm.processing ? 'Saving…' : 'Save' }}
                </UiButton>
            </template>
        </UiModal>
    </AppLayout>
</template>

<style>
.rich-text-content ul {
    list-style: disc;
    padding-left: 1.25rem;
    margin: 0.35rem 0;
}

.rich-text-content ol {
    list-style: decimal;
    padding-left: 1.25rem;
    margin: 0.35rem 0;
}

.rich-text-content h2 {
    font-size: 1.05rem;
    font-weight: 600;
    margin: 0.4rem 0;
}

.rich-text-content h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0.3rem 0;
}

.rich-text-content p {
    margin: 0.25rem 0;
}

.rich-text-content strong {
    font-weight: 700;
}

.rich-text-content em {
    font-style: italic;
}

.rich-text-content u {
    text-decoration: underline;
}
</style>
