<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import LevelsPanel from '@/pages/CompanyStructure/components/LevelsPanel.vue';
import type { StructureGrade, StructureLevel, StructureNode } from '@/types/companyStructure';

const props = defineProps<{
    nodes: StructureNode[];
    groupId: number;
    depth?: number;
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
}>();

const emit = defineEmits<{
    addNode: [parentId: number | null];
    editNode: [node: StructureNode];
    moveNode: [node: StructureNode];
    addLevel: [node: StructureNode];
    editLevel: [level: StructureLevel];
    addGrade: [level: StructureLevel];
    editGrade: [grade: StructureGrade, level: StructureLevel];
}>();

const expanded = ref<Record<number, boolean>>({});

function toggle(id: number) {
    expanded.value[id] = !expanded.value[id];
}

function deleteNode(node: StructureNode) {
    if (!confirm(`Delete subgroup "${node.name}"?`)) {
        return;
    }
    router.delete(`/company-structure/nodes/${node.id}`, { preserveScroll: true });
}

function moveSibling(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= props.nodes.length) {
        return;
    }
    const ordered = props.nodes.map((node) => node.id);
    const [item] = ordered.splice(index, 1);
    ordered.splice(target, 0, item);
    router.post(
        '/company-structure/nodes/reorder',
        {
            ordered_ids: ordered,
            parent_id: props.nodes[0]?.parent_id ?? null,
            structure_group_id: props.groupId,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="space-y-2" :class="depth ? 'ml-4 border-l border-slate-200 pl-3 dark:border-slate-700' : ''">
        <article
            v-for="(node, index) in nodes"
            :key="node.id"
            class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-surface"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <button type="button" class="min-w-0 text-left" @click="toggle(node.id)">
                    <div class="font-semibold text-slate-900 dark:text-white">
                        <span class="mr-1 text-slate-400">{{ expanded[node.id] ? '▾' : '▸' }}</span>
                        {{ node.name }}
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        <span v-if="node.code">{{ node.code }} · </span>
                        Head: {{ node.head_employee?.name ?? '—' }}
                        · {{ node.is_active ? 'Active' : 'Inactive' }}
                    </p>
                </button>
                <div class="flex flex-wrap gap-1">
                    <UiButton v-if="canUpdate" size="sm" variant="ghost" :disabled="index === 0" @click="moveSibling(index, -1)">Up</UiButton>
                    <UiButton
                        v-if="canUpdate"
                        size="sm"
                        variant="ghost"
                        :disabled="index === nodes.length - 1"
                        @click="moveSibling(index, 1)"
                    >
                        Down
                    </UiButton>
                    <UiButton v-if="canUpdate" size="sm" variant="ghost" @click="emit('editNode', node)">Edit</UiButton>
                    <UiButton v-if="canUpdate" size="sm" variant="ghost" @click="emit('moveNode', node)">Move</UiButton>
                    <UiButton v-if="canCreate" size="sm" variant="ghost" @click="emit('addNode', node.id)">Add child</UiButton>
                    <UiButton v-if="canCreate" size="sm" variant="ghost" @click="emit('addLevel', node)">Add level</UiButton>
                    <UiButton v-if="canDelete" size="sm" variant="ghost" @click="deleteNode(node)">Delete</UiButton>
                </div>
            </div>

            <div v-if="expanded[node.id]" class="mt-3 space-y-3">
                <LevelsPanel
                    :levels="node.levels"
                    :can-create="canCreate"
                    :can-update="canUpdate"
                    :can-delete="canDelete"
                    @add-level="emit('addLevel', node)"
                    @edit-level="emit('editLevel', $event)"
                    @add-grade="emit('addGrade', $event)"
                    @edit-grade="(grade, level) => emit('editGrade', grade, level)"
                />

                <NodeTree
                    v-if="node.children.length"
                    :nodes="node.children"
                    :group-id="groupId"
                    :depth="(depth ?? 0) + 1"
                    :can-create="canCreate"
                    :can-update="canUpdate"
                    :can-delete="canDelete"
                    @add-node="emit('addNode', $event)"
                    @edit-node="emit('editNode', $event)"
                    @move-node="emit('moveNode', $event)"
                    @add-level="emit('addLevel', $event)"
                    @edit-level="emit('editLevel', $event)"
                    @add-grade="emit('addGrade', $event)"
                    @edit-grade="(grade, level) => emit('editGrade', grade, level)"
                />
            </div>
        </article>
    </div>
</template>

<script lang="ts">
export default {
    name: 'NodeTree',
};
</script>
