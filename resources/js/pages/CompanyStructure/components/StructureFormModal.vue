<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import type {
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
    is_active: true,
});

const levelForm = useForm({
    level_number: 1,
    reference_title: '',
    structure_group_id: null as number | null,
    structure_node_id: null as number | null,
});

const gradeForm = useForm({
    grade: '',
    title: '',
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
                nodeForm.is_active = props.node.is_active;
            } else {
                nodeForm.reset();
                nodeForm.parent_id = props.parentId ?? null;
                nodeForm.head_grade_ids = [];
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
    <UiModal :open="open" :title="title" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <template v-if="mode === 'node'">
                <UiInput v-model="nodeForm.name" label="Name" :error="nodeForm.errors.name" required />
                <UiInput v-model="nodeForm.code" label="Code" :error="nodeForm.errors.code" />
                <UiInput v-model="nodeForm.description" label="Description" :error="nodeForm.errors.description" />

                <div>
                    <p class="mb-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">Head grades</p>
                    <p v-if="nodeForm.errors.head_grade_ids" class="mb-2 text-sm text-red-600">
                        {{ nodeForm.errors.head_grade_ids }}
                    </p>
                    <div
                        v-if="headGradeOptions.length === 0"
                        class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-sm text-slate-500 dark:border-slate-700"
                    >
                        No eligible grades yet. Add grades on this subgroup or its parent first.
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
                        Select one or more grades. Leave approvals use employees in those grades.
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
                <UiInput v-model="gradeForm.grade" label="Grade" :error="gradeForm.errors.grade" required />
                <UiInput v-model="gradeForm.title" label="Title" :error="gradeForm.errors.title" required />
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
