<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import UiActionMenu from '@/components/ui/UiActionMenu.vue';
import UiActionMenuItem from '@/components/ui/UiActionMenuItem.vue';
import UiIconButton from '@/components/ui/UiIconButton.vue';
import LevelsPanel from '@/pages/CompanyStructure/components/LevelsPanel.vue';
import type {
    StructureGrade,
    StructureGroupOption,
    StructureLevel,
    StructureNode,
} from '@/types/companyStructure';

const props = defineProps<{
    nodes: StructureNode[];
    groupOptions: StructureGroupOption[];
    depth?: number;
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
}>();

const emit = defineEmits<{
    addNode: [payload: { parentId: number | null; structureGroupId: number }];
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

function groupOptionForCode(code: string): StructureGroupOption | undefined {
    return props.groupOptions.find((option) => option.code === code);
}

function childAddActions(node: StructureNode): StructureGroupOption[] {
    return (node.allowed_child_codes ?? [])
        .map((code) => groupOptionForCode(code))
        .filter((option): option is StructureGroupOption => Boolean(option));
}

function typeBadge(node: StructureNode): string {
    return node.group_name ?? node.group_code ?? 'Node';
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
                    <div class="flex flex-wrap items-center gap-2 font-semibold text-slate-900 dark:text-white">
                        <span class="text-slate-400">{{ expanded[node.id] ? '▾' : '▸' }}</span>
                        <span>{{ node.name }}</span>
                        <span
                            class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ typeBadge(node) }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        <span v-if="node.code">{{ node.code }} · </span>
                        Head grades:
                        <template v-if="node.head_grades?.length">
                            {{ node.head_grades.map((grade) => grade.label).join(', ') }}
                        </template>
                        <template v-else>—</template>
                        · {{ node.is_active ? 'Active' : 'Inactive' }}
                    </p>
                </button>
                <div class="flex flex-wrap items-center gap-0.5">
                    <template v-if="canUpdate">
                        <UiIconButton label="Move up" :disabled="index === 0" @click="moveSibling(index, -1)">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M10 3a.75.75 0 0 1 .53.22l4.25 4.25a.75.75 0 1 1-1.06 1.06L10 4.81 6.28 8.53a.75.75 0 0 1-1.06-1.06l4.25-4.25A.75.75 0 0 1 10 3Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </UiIconButton>
                        <UiIconButton
                            label="Move down"
                            :disabled="index === nodes.length - 1"
                            @click="moveSibling(index, 1)"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M10 17a.75.75 0 0 1-.53-.22l-4.25-4.25a.75.75 0 1 1 1.06-1.06L10 15.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25A.75.75 0 0 1 10 17Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </UiIconButton>
                    </template>

                    <UiActionMenu v-if="canCreate" label="Add">
                        <UiActionMenuItem
                            v-for="childGroup in childAddActions(node)"
                            :key="`${node.id}-${childGroup.code}`"
                            @click="emit('addNode', { parentId: node.id, structureGroupId: childGroup.id })"
                        >
                            Add {{ childGroup.name }}
                        </UiActionMenuItem>
                        <UiActionMenuItem @click="emit('addLevel', node)">Add level</UiActionMenuItem>
                    </UiActionMenu>

                    <UiActionMenu v-if="canUpdate || canDelete" label="More">
                        <UiActionMenuItem v-if="canUpdate" @click="emit('editNode', node)">Edit</UiActionMenuItem>
                        <UiActionMenuItem v-if="canUpdate" @click="emit('moveNode', node)">Move</UiActionMenuItem>
                        <UiActionMenuItem v-if="canDelete" danger @click="deleteNode(node)">Delete</UiActionMenuItem>
                    </UiActionMenu>
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
                    :group-options="groupOptions"
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
