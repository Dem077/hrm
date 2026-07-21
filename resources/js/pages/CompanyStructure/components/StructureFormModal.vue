<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import type { StructureGrade, StructureHeadOption, StructureLevel, StructureNode } from '@/types/companyStructure';

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
    headOptions: StructureHeadOption[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const nodeForm = useForm({
    name: '',
    code: '',
    description: '',
    parent_id: null as number | null,
    head_employee_id: '' as number | string,
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
                nodeForm.head_employee_id = props.node.head_employee_id ?? '';
                nodeForm.is_active = props.node.is_active;
            } else {
                nodeForm.reset();
                nodeForm.parent_id = props.parentId ?? null;
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
                <UiSelect v-model="nodeForm.head_employee_id" label="Head" :error="nodeForm.errors.head_employee_id">
                    <option value="">No head</option>
                    <option v-for="head in headOptions" :key="head.id" :value="head.id">
                        {{ head.name }} ({{ head.staff_id }})
                    </option>
                </UiSelect>
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
