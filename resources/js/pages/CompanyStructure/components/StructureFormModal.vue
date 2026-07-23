<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiRichTextEditor from '@/components/ui/UiRichTextEditor.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import type {
    ApprovalTemplateOption,
    StructureGrade,
    StructureGroup,
    StructureHeadGradeOption,
    StructureLevel,
    StructureNode,
} from '@/types/companyStructure';

const props = defineProps<{
    open: boolean;
    mode: 'node' | 'level' | 'grade';
    title: string;
    groupId?: number | null;
    parentId?: number | null;
    nodeId?: number | null;
    levelId?: number | null;
    node?: StructureNode | null;
    level?: StructureLevel | null;
    grade?: StructureGrade | null;
    groups: StructureGroup[];
    approvalTemplates?: ApprovalTemplateOption[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const nodeForm = useForm({
    name: '',
    code: '',
    description: '',
    parent_id: null as number | null,
    head_grade_ids: [] as number[],
    leave_approval_template_id: null as number | null,
    overtime_approval_template_id: null as number | null,
    is_active: true,
});

const leaveTemplateOptions = computed(() =>
    (props.approvalTemplates ?? []).filter((template) => template.kind === 'leave'),
);

const overtimeTemplateOptions = computed(() =>
    (props.approvalTemplates ?? []).filter((template) => template.kind === 'overtime'),
);

const selectedLeaveTemplate = computed(
    () =>
        leaveTemplateOptions.value.find((template) => template.id === nodeForm.leave_approval_template_id) ?? null,
);

const selectedOvertimeTemplate = computed(
    () =>
        overtimeTemplateOptions.value.find((template) => template.id === nodeForm.overtime_approval_template_id) ??
        null,
);

function templateOptionLabel(option: ApprovalTemplateOption): string {
    if (option.steps_summary) {
        return `${option.name} — ${option.steps_summary}`;
    }

    return option.name;
}

function templateStructureSummary(option: ApprovalTemplateOption): string {
    const labels = (option.steps ?? [])
        .filter((step) => step.enabled && step.key !== 'direct_manager' && step.key !== 'hr')
        .map((step) => step.label);

    const structure = labels.length ? `${labels.join(' → ')} → HR` : 'HR only';

    return `With manager: Manager → HR · Without: ${structure}`;
}

const levelForm = useForm({
    level_number: 1,
    reference_title: '',
    structure_group_id: null as number | null,
    structure_node_id: null as number | null,
});

const gradeForm = useForm({
    grade: '',
    title: '',
    requirements: '',
    job_description: '',
    is_active: true,
});

const strategicGroup = computed(
    () => props.groups.find((group) => group.code === 'strategic_leadership') ?? null,
);

const organizationGroup = computed(
    () => props.groups.find((group) => group.is_org_tree || group.code === 'organization') ?? null,
);

function findNode(nodes: StructureNode[], id: number): StructureNode | null {
    for (const node of nodes) {
        if (node.id === id) {
            return node;
        }
        const nested = findNode(node.children ?? [], id);
        if (nested) {
            return nested;
        }
    }
    return null;
}

function gradesFromLevels(
    levels: StructureLevel[],
    source: 'current' | 'parent',
    sourceLabel: string,
): StructureHeadGradeOption[] {
    const options: StructureHeadGradeOption[] = [];
    for (const level of levels) {
        for (const grade of level.grades ?? []) {
            if (!grade.is_active) {
                continue;
            }
            options.push({
                id: grade.id,
                label: grade.label,
                source,
                source_label: sourceLabel,
            });
        }
    }
    return options;
}

const headGradeOptions = computed(() => {
    if (props.mode !== 'node') {
        return [] as StructureHeadGradeOption[];
    }

    const seen = new Set<number>();
    const options: StructureHeadGradeOption[] = [];

    const pushUnique = (items: StructureHeadGradeOption[]) => {
        for (const item of items) {
            if (seen.has(item.id)) {
                continue;
            }
            seen.add(item.id);
            options.push(item);
        }
    };

    const currentNode = props.node ?? null;
    if (currentNode) {
        pushUnique(gradesFromLevels(currentNode.levels ?? [], 'current', currentNode.name));
    }

    const parentId = currentNode?.parent_id ?? props.parentId ?? null;
    if (parentId && organizationGroup.value) {
        const parent = findNode(organizationGroup.value.nodes, parentId);
        if (parent) {
            pushUnique(gradesFromLevels(parent.levels ?? [], 'parent', parent.name));
        }
    } else if (strategicGroup.value) {
        pushUnique(gradesFromLevels(strategicGroup.value.levels ?? [], 'parent', strategicGroup.value.name));
    }

    return options;
});

const headGradesBySource = computed(() => {
    const current = headGradeOptions.value.filter((option) => option.source === 'current');
    const parent = headGradeOptions.value.filter((option) => option.source === 'parent');
    return { current, parent };
});

function isHeadSelected(gradeId: number): boolean {
    return nodeForm.head_grade_ids.includes(gradeId);
}

function toggleHeadGrade(gradeId: number) {
    if (isHeadSelected(gradeId)) {
        nodeForm.head_grade_ids = nodeForm.head_grade_ids.filter((id) => id !== gradeId);
    } else {
        nodeForm.head_grade_ids = [...nodeForm.head_grade_ids, gradeId];
    }
}

watch(
    () => [props.open, props.mode, props.node, props.level, props.grade] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'node') {
            nodeForm.clearErrors();
            if (props.node) {
                nodeForm.name = props.node.name;
                nodeForm.code = props.node.code ?? '';
                nodeForm.description = props.node.description ?? '';
                nodeForm.parent_id = props.node.parent_id;
                nodeForm.head_grade_ids = [...(props.node.head_grade_ids ?? [])];
                nodeForm.leave_approval_template_id = props.node.leave_approval_template_id ?? null;
                nodeForm.overtime_approval_template_id = props.node.overtime_approval_template_id ?? null;
                nodeForm.is_active = props.node.is_active;
            } else {
                nodeForm.reset();
                nodeForm.parent_id = props.parentId ?? null;
                nodeForm.head_grade_ids = [];
                nodeForm.leave_approval_template_id = null;
                nodeForm.overtime_approval_template_id = null;
                nodeForm.is_active = true;
            }
        }

        if (props.mode === 'level') {
            levelForm.clearErrors();
            if (props.level) {
                levelForm.level_number = props.level.level_number;
                levelForm.reference_title = props.level.reference_title;
                levelForm.structure_group_id = props.level.structure_group_id;
                levelForm.structure_node_id = props.level.structure_node_id;
            } else {
                levelForm.reset();
                levelForm.level_number = 1;
                levelForm.structure_group_id = props.groupId ?? null;
                levelForm.structure_node_id = props.nodeId ?? null;
            }
        }

        if (props.mode === 'grade') {
            gradeForm.clearErrors();
            if (props.grade) {
                gradeForm.grade = props.grade.grade;
                gradeForm.title = props.grade.title;
                gradeForm.requirements = props.grade.requirements ?? '';
                gradeForm.job_description = props.grade.job_description ?? '';
                gradeForm.is_active = props.grade.is_active;
            } else {
                gradeForm.reset();
                gradeForm.is_active = true;
            }
        }
    },
    { immediate: true },
);

function submit() {
    if (props.mode === 'node') {
        if (props.node?.id) {
            nodeForm.put(`/company-structure/nodes/${props.node.id}`, {
                preserveScroll: true,
                onSuccess: () => emit('close'),
            });
        } else if (props.groupId) {
            nodeForm.post(`/company-structure/groups/${props.groupId}/nodes`, {
                preserveScroll: true,
                onSuccess: () => emit('close'),
            });
        }
        return;
    }

    if (props.mode === 'level') {
        if (props.level?.id) {
            levelForm.put(`/company-structure/levels/${props.level.id}`, {
                preserveScroll: true,
                onSuccess: () => emit('close'),
            });
        } else {
            levelForm.post('/company-structure/levels', {
                preserveScroll: true,
                onSuccess: () => emit('close'),
            });
        }
        return;
    }

    if (props.grade?.id) {
        gradeForm.put(`/company-structure/grades/${props.grade.id}`, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    } else if (props.levelId) {
        gradeForm.post(`/company-structure/levels/${props.levelId}/grades`, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    }
}
</script>

<template>
    <UiModal :open="open" :title="title" :max-width="mode === 'node' || mode === 'grade' ? 'lg' : 'md'" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <template v-if="mode === 'node'">
                <UiInput v-model="nodeForm.name" label="Name" :error="nodeForm.errors.name" required />
                <UiInput v-model="nodeForm.code" label="Code" :error="nodeForm.errors.code" />
                <UiInput v-model="nodeForm.description" label="Description" :error="nodeForm.errors.description" />

                <div class="space-y-4">
                    <div>
                        <UiSelect
                            :model-value="nodeForm.leave_approval_template_id ?? ''"
                            label="Leave approval template"
                            hint="Inherit uses the nearest parent assignment, then the company default."
                            :error="nodeForm.errors.leave_approval_template_id"
                            @update:model-value="nodeForm.leave_approval_template_id = $event ? Number($event) : null"
                        >
                            <option value="">Inherit</option>
                            <option v-for="option in leaveTemplateOptions" :key="option.id" :value="option.id">
                                {{ templateOptionLabel(option) }}
                            </option>
                        </UiSelect>
                        <div
                            v-if="selectedLeaveTemplate"
                            class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-surface-elevated"
                        >
                            <p class="font-medium text-slate-800 dark:text-slate-100">
                                {{ selectedLeaveTemplate.name }}
                            </p>
                            <p
                                v-if="selectedLeaveTemplate.description"
                                class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ selectedLeaveTemplate.description }}
                            </p>
                            <p class="mt-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                                {{ templateStructureSummary(selectedLeaveTemplate) }}
                            </p>
                            <ul
                                v-if="selectedLeaveTemplate.steps?.length"
                                class="mt-2 space-y-1 border-t border-slate-200 pt-2 dark:border-slate-700"
                            >
                                <li class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
                                    Direct manager → HR
                                    <span class="text-slate-500">(bypass when manager is assigned)</span>
                                </li>
                                <li
                                    v-for="step in selectedLeaveTemplate.steps.filter((s) => s.key !== 'direct_manager')"
                                    :key="`leave-${step.key}`"
                                    class="flex items-center gap-2 text-xs"
                                    :class="
                                        step.enabled
                                            ? 'text-slate-700 dark:text-slate-200'
                                            : 'text-slate-400 line-through dark:text-slate-500'
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 shrink-0 rounded-full"
                                        :class="step.enabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"
                                    />
                                    {{ step.label }}
                                    <span v-if="!step.enabled" class="no-underline">(off)</span>
                                </li>
                                <li class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" />
                                    HR (final approval)
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <UiSelect
                            :model-value="nodeForm.overtime_approval_template_id ?? ''"
                            label="Overtime approval template"
                            hint="Inherit uses the nearest parent assignment, then the company default."
                            :error="nodeForm.errors.overtime_approval_template_id"
                            @update:model-value="
                                nodeForm.overtime_approval_template_id = $event ? Number($event) : null
                            "
                        >
                            <option value="">Inherit</option>
                            <option v-for="option in overtimeTemplateOptions" :key="option.id" :value="option.id">
                                {{ templateOptionLabel(option) }}
                            </option>
                        </UiSelect>
                        <div
                            v-if="selectedOvertimeTemplate"
                            class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-surface-elevated"
                        >
                            <p class="font-medium text-slate-800 dark:text-slate-100">
                                {{ selectedOvertimeTemplate.name }}
                            </p>
                            <p
                                v-if="selectedOvertimeTemplate.description"
                                class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ selectedOvertimeTemplate.description }}
                            </p>
                            <p class="mt-1.5 text-xs font-medium text-slate-600 dark:text-slate-300">
                                {{ templateStructureSummary(selectedOvertimeTemplate) }}
                            </p>
                            <ul
                                v-if="selectedOvertimeTemplate.steps?.length"
                                class="mt-2 space-y-1 border-t border-slate-200 pt-2 dark:border-slate-700"
                            >
                                <li class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
                                    Direct manager → HR
                                    <span class="text-slate-500">(bypass when manager is assigned)</span>
                                </li>
                                <li
                                    v-for="step in selectedOvertimeTemplate.steps.filter((s) => s.key !== 'direct_manager')"
                                    :key="`ot-${step.key}`"
                                    class="flex items-center gap-2 text-xs"
                                    :class="
                                        step.enabled
                                            ? 'text-slate-700 dark:text-slate-200'
                                            : 'text-slate-400 line-through dark:text-slate-500'
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 shrink-0 rounded-full"
                                        :class="step.enabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"
                                    />
                                    {{ step.label }}
                                    <span v-if="!step.enabled" class="no-underline">(off)</span>
                                </li>
                                <li class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" />
                                    HR (final approval)
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="mb-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">Head designations</p>
                    <p v-if="nodeForm.errors.head_grade_ids" class="mb-2 text-sm text-red-600">
                        {{ nodeForm.errors.head_grade_ids }}
                    </p>
                    <div
                        v-if="headGradeOptions.length === 0"
                        class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500 dark:border-slate-700"
                    >
                        No eligible designations yet. Add designations on this subgroup or its parent first.
                    </div>
                    <div v-else class="max-h-56 space-y-3 overflow-y-auto rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div v-if="headGradesBySource.current.length">
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                This subgroup · {{ headGradesBySource.current[0]?.source_label }}
                            </p>
                            <div class="space-y-1.5">
                                <label
                                    v-for="option in headGradesBySource.current"
                                    :key="`c-${option.id}`"
                                    class="flex cursor-pointer items-start gap-2 rounded-md px-1 py-1 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/60"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 rounded border-slate-300"
                                        :checked="isHeadSelected(option.id)"
                                        @change="toggleHeadGrade(option.id)"
                                    />
                                    <span>{{ option.label }}</span>
                                </label>
                            </div>
                        </div>
                        <div v-if="headGradesBySource.parent.length">
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Parent · {{ headGradesBySource.parent[0]?.source_label }}
                            </p>
                            <div class="space-y-1.5">
                                <label
                                    v-for="option in headGradesBySource.parent"
                                    :key="`p-${option.id}`"
                                    class="flex cursor-pointer items-start gap-2 rounded-md px-1 py-1 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/60"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 rounded border-slate-300"
                                        :checked="isHeadSelected(option.id)"
                                        @change="toggleHeadGrade(option.id)"
                                    />
                                    <span>{{ option.label }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">
                        Select one or more designations. Leave approvals use employees in those designations.
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="nodeForm.is_active" type="checkbox" class="rounded border-slate-300" />
                    Active
                </label>
            </template>

            <template v-else-if="mode === 'level'">
                <UiInput v-model="levelForm.level_number" type="number" min="1" label="Level" :error="levelForm.errors.level_number" required />
                <UiInput v-model="levelForm.reference_title" label="Reference title" :error="levelForm.errors.reference_title" required />
            </template>

            <template v-else>
                <UiInput v-model="gradeForm.grade" label="Grade code" :error="gradeForm.errors.grade" required />
                <UiInput v-model="gradeForm.title" label="Designation title" :error="gradeForm.errors.title" required />
                <UiRichTextEditor
                    v-model="gradeForm.requirements"
                    label="Requirements"
                    required
                    placeholder="Education, experience, skills, and other requirements for this designation."
                    :error="gradeForm.errors.requirements"
                />
                <UiRichTextEditor
                    v-model="gradeForm.job_description"
                    label="Job description"
                    hint="Optional"
                    placeholder="Optional summary of duties and responsibilities."
                    :error="gradeForm.errors.job_description"
                />
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input v-model="gradeForm.is_active" type="checkbox" class="rounded border-slate-300" />
                    Active
                </label>
            </template>

            <div class="flex justify-end gap-2 pt-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" variant="primary" :disabled="nodeForm.processing || levelForm.processing || gradeForm.processing">
                    Save
                </UiButton>
            </div>
        </form>
    </UiModal>
</template>
