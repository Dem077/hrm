<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import { usePermissions } from '@/composables/usePermissions';

export type TemplateStep = {
    key: string;
    label: string;
    description: string;
    enabled: boolean;
    locked: boolean;
    is_structure_head: boolean;
    head_grade_ids: number[];
};

export type ApprovalTemplateRow = {
    id: number;
    kind: 'leave' | 'overtime';
    name: string;
    description: string | null;
    is_system: boolean;
    in_use: boolean;
    sort_order: number;
    steps: TemplateStep[];
};

export type ApprovalTemplatesPayload = {
    templates: ApprovalTemplateRow[];
    company_defaults: {
        leave_approval_template_id: number | null;
        overtime_approval_template_id: number | null;
    };
    empty_steps: TemplateStep[];
};

const props = defineProps<{
    approvalTemplates: ApprovalTemplatesPayload;
}>();

const { can } = usePermissions();
const canEdit = computed(() => can('attendance-settings.leave-workflow.update'));

const selectedKind = ref<'leave' | 'overtime'>('leave');
const modalOpen = ref(false);
const editing = ref<ApprovalTemplateRow | null>(null);

const templatesForKind = computed(() =>
    props.approvalTemplates.templates.filter((template) => template.kind === selectedKind.value),
);

const leaveOptions = computed(() => props.approvalTemplates.templates.filter((t) => t.kind === 'leave'));
const overtimeOptions = computed(() => props.approvalTemplates.templates.filter((t) => t.kind === 'overtime'));

const defaultsForm = useForm({
    leave_approval_template_id: props.approvalTemplates.company_defaults.leave_approval_template_id as number | null,
    overtime_approval_template_id: props.approvalTemplates.company_defaults.overtime_approval_template_id as number | null,
});

watch(
    () => props.approvalTemplates.company_defaults,
    (defaults) => {
        defaultsForm.defaults({
            leave_approval_template_id: defaults.leave_approval_template_id,
            overtime_approval_template_id: defaults.overtime_approval_template_id,
        });
        defaultsForm.reset();
        defaultsForm.clearErrors();
    },
    { deep: true },
);

const form = useForm({
    name: '',
    description: '',
    steps: [] as TemplateStep[],
});

function cloneSteps(steps: TemplateStep[]): TemplateStep[] {
    return steps.map((step) => ({
        ...step,
        head_grade_ids: [...(step.head_grade_ids ?? [])],
    }));
}

function openEdit(template: ApprovalTemplateRow) {
    editing.value = template;
    form.clearErrors();
    form.name = template.name;
    form.description = template.description ?? '';
    form.steps = cloneSteps(template.steps);
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editing.value = null;
}

function moveStep(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= form.steps.length) {
        return;
    }

    if (form.steps[index]?.locked || form.steps[target]?.locked) {
        return;
    }

    const steps = [...form.steps];
    const [item] = steps.splice(index, 1);
    steps.splice(target, 0, item);
    form.steps = steps;
}

function enabledSummary(template: ApprovalTemplateRow): string {
    const labels = template.steps
        .filter((step) => step.enabled && step.key !== 'hr' && step.key !== 'direct_manager')
        .map((step) => step.label);

    const structure = labels.length ? labels.join(' → ') + ' → HR' : 'HR only';

    return `With manager: Manager → HR · Without: ${structure}`;
}

function saveDefaults() {
    defaultsForm
        .transform((data) => ({
            leave_approval_template_id: data.leave_approval_template_id || null,
            overtime_approval_template_id: data.overtime_approval_template_id || null,
        }))
        .put('/attendance-settings/approval-template-defaults', {
            preserveScroll: true,
        });
}

function saveTemplate() {
    if (!editing.value) {
        return;
    }

    form
        .transform((data) => ({
            name: data.name,
            description: data.description || null,
            steps: data.steps
                .filter((step) => step.key !== 'hr')
                .map((step) => ({
                    key: step.key,
                    enabled: step.enabled,
                    head_grade_ids: [],
                })),
        }))
        .put(`/attendance-settings/approval-templates/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
}
</script>

<template>
    <div class="space-y-6">
        <UiCard
            title="Company default templates"
            description="Used when a structure node has no template assigned (Inherit). Strategic Leadership staff without a branch also use these defaults."
        >
            <form class="space-y-4" @submit.prevent="saveDefaults">
                <div class="grid gap-4 sm:grid-cols-2">
                    <UiSelect
                        :model-value="defaultsForm.leave_approval_template_id ?? ''"
                        label="Leave default"
                        :error="defaultsForm.errors.leave_approval_template_id"
                        :disabled="!canEdit"
                        @update:model-value="defaultsForm.leave_approval_template_id = $event ? Number($event) : null"
                    >
                        <option value="">Select template</option>
                        <option v-for="option in leaveOptions" :key="option.id" :value="option.id">
                            {{ option.name }}
                        </option>
                    </UiSelect>
                    <UiSelect
                        :model-value="defaultsForm.overtime_approval_template_id ?? ''"
                        label="Overtime default"
                        :error="defaultsForm.errors.overtime_approval_template_id"
                        :disabled="!canEdit"
                        @update:model-value="defaultsForm.overtime_approval_template_id = $event ? Number($event) : null"
                    >
                        <option value="">Select template</option>
                        <option v-for="option in overtimeOptions" :key="option.id" :value="option.id">
                            {{ option.name }}
                        </option>
                    </UiSelect>
                </div>
                <div v-if="canEdit" class="flex justify-end">
                    <UiButton type="submit" variant="primary" :disabled="defaultsForm.processing">
                        Save defaults
                    </UiButton>
                </div>
            </form>
        </UiCard>

        <UiCard
            title="Approval templates"
            description="Short, Standard, and Full only for now. These define structure-head chains for employees without a direct manager. If a direct manager is assigned, approval always goes Manager → HR."
        >
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="inline-flex gap-1 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-surface-muted">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-1.5 text-sm font-medium transition"
                            :class="
                                selectedKind === 'leave'
                                    ? 'bg-brand-600 text-white'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                            "
                            @click="selectedKind = 'leave'"
                        >
                            Leave
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-4 py-1.5 text-sm font-medium transition"
                            :class="
                                selectedKind === 'overtime'
                                    ? 'bg-brand-600 text-white'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                            "
                            @click="selectedKind = 'overtime'"
                        >
                            Overtime
                        </button>
                    </div>
                </div>

                <div v-if="templatesForKind.length === 0" class="text-sm text-slate-500">
                    No {{ selectedKind }} templates yet.
                </div>

                <ul v-else class="space-y-2">
                    <li
                        v-for="template in templatesForKind"
                        :key="template.id"
                        class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ template.name }}
                                </p>
                                <p v-if="template.description" class="mt-0.5 text-xs text-slate-500">
                                    {{ template.description }}
                                </p>
                                <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                    {{ enabledSummary(template) }}
                                </p>
                            </div>
                            <div v-if="canEdit" class="flex gap-1">
                                <UiButton type="button" size="sm" variant="ghost" @click="openEdit(template)">
                                    Edit
                                </UiButton>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </UiCard>

        <UiModal
            :open="modalOpen"
            :title="editing ? `Edit ${editing.name}` : 'Edit template'"
            description="Turn structure head steps on or off and set their order. Direct manager is a fixed bypass (Manager → HR when assigned). HR is always last."
            max-width="lg"
            @close="closeModal"
        >
            <form class="space-y-4" @submit.prevent="saveTemplate">
                <UiInput v-model="form.name" label="Name" required :error="form.errors.name" disabled />
                <UiInput v-model="form.description" label="Description" :error="form.errors.description" />

                <ol class="space-y-2">
                    <li
                        v-for="(step, index) in form.steps"
                        :key="step.key"
                        class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <label class="flex min-w-0 flex-1 items-start gap-3">
                                <input
                                    v-model="step.enabled"
                                    type="checkbox"
                                    class="mt-1 rounded border-slate-300"
                                    :disabled="step.locked || !canEdit"
                                />
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ index + 1 }}. {{ step.label }}
                                    </span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ step.description }}</span>
                                </span>
                            </label>
                            <div v-if="!step.locked && canEdit" class="flex gap-1">
                                <UiButton type="button" size="sm" variant="ghost" :disabled="index === 0" @click="moveStep(index, -1)">
                                    Up
                                </UiButton>
                                <UiButton
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    :disabled="index >= form.steps.filter((item) => !item.locked).length - 1"
                                    @click="moveStep(index, 1)"
                                >
                                    Down
                                </UiButton>
                            </div>
                        </div>
                    </li>
                </ol>

                <p v-if="form.errors.steps" class="text-xs text-red-600">
                    {{ form.errors.steps }}
                </p>
            </form>

            <template #footer>
                <UiButton type="button" variant="ghost" @click="closeModal">Cancel</UiButton>
                <UiButton v-if="canEdit" type="button" variant="primary" :disabled="form.processing" @click="saveTemplate">
                    Save template
                </UiButton>
            </template>
        </UiModal>
    </div>
</template>
