<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import type { StructureGroup, StructureNode } from '@/types/companyStructure';

const props = defineProps<{
    open: boolean;
    node: StructureNode | null;
    groups: StructureGroup[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const form = useForm({
    structure_group_id: '' as number | string,
    parent_id: '' as number | string,
});

type ParentOption = {
    id: number;
    label: string;
    groupId: number;
};

const movableGroups = computed(() => props.groups.filter((group) => group.allows_nodes));

const parentOptions = computed(() => {
    if (!props.node) {
        return [] as ParentOption[];
    }

    const excluded = new Set<number>([props.node.id, ...collectDescendantIds(props.node)]);
    const options: ParentOption[] = [];

    for (const group of movableGroups.value) {
        collectParentOptions(group.nodes, [], group, excluded, options);
    }

    return options;
});

const parentsInSelectedGroup = computed(() =>
    parentOptions.value.filter((option) => String(option.groupId) === String(form.structure_group_id)),
);

watch(
    () => [props.open, props.node] as const,
    ([open, node]) => {
        if (!open || !node) {
            return;
        }

        form.clearErrors();
        form.structure_group_id = node.structure_group_id;
        form.parent_id = node.parent_id ?? '';
    },
    { immediate: true },
);

watch(
    () => form.structure_group_id,
    () => {
        if (!parentsInSelectedGroup.value.some((option) => String(option.id) === String(form.parent_id))) {
            form.parent_id = '';
        }
    },
);

function collectDescendantIds(node: StructureNode): number[] {
    const ids: number[] = [];
    for (const child of node.children ?? []) {
        ids.push(child.id, ...collectDescendantIds(child));
    }
    return ids;
}

function collectParentOptions(
    nodes: StructureNode[],
    ancestors: string[],
    group: StructureGroup,
    excluded: Set<number>,
    options: ParentOption[],
): void {
    for (const node of nodes) {
        if (excluded.has(node.id)) {
            continue;
        }

        const path = [...ancestors, node.name];
        options.push({
            id: node.id,
            label: `${group.name} › ${path.join(' › ')}`,
            groupId: group.id,
        });

        if (node.children?.length) {
            collectParentOptions(node.children, path, group, excluded, options);
        }
    }
}

function submit() {
    if (!props.node?.id) {
        return;
    }

    form
        .transform((data) => ({
            structure_group_id: Number(data.structure_group_id),
            parent_id: data.parent_id === '' || data.parent_id === null ? null : Number(data.parent_id),
        }))
        .post(`/company-structure/nodes/${props.node.id}/move`, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
}
</script>

<template>
    <UiModal
        :open="open"
        title="Move subgroup"
        :description="node ? `Move “${node.name}” to another group or parent.` : undefined"
        @close="emit('close')"
    >
        <form v-if="node" class="space-y-4" @submit.prevent="submit">
            <UiSelect
                v-model="form.structure_group_id"
                label="Destination group"
                :error="form.errors.structure_group_id"
                required
            >
                <option v-for="group in movableGroups" :key="group.id" :value="group.id">
                    {{ group.name }}
                </option>
            </UiSelect>

            <UiSelect v-model="form.parent_id" label="Parent subgroup" :error="form.errors.parent_id">
                <option value="">Top level of selected group</option>
                <option v-for="option in parentsInSelectedGroup" :key="option.id" :value="option.id">
                    {{ option.label }}
                </option>
            </UiSelect>

            <p class="text-xs text-slate-500">
                Strategic Leadership cannot receive subgroups. Child subgroups move with this node.
            </p>

            <div class="flex justify-end gap-2 pt-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" variant="primary" :disabled="form.processing">Move</UiButton>
            </div>
        </form>
    </UiModal>
</template>
