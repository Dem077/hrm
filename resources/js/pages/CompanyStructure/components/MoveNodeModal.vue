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
    parent_id: '' as number | string,
});

type ParentOption = {
    id: number;
    label: string;
};

const orgTree = computed(() => props.groups.find((group) => group.is_org_tree || group.code === 'organization') ?? null);

const treeNode = computed(() => {
    if (!props.node || !orgTree.value) {
        return props.node;
    }

    return findNode(orgTree.value.nodes, props.node.id) ?? props.node;
});

const allowedParentCodes = computed(() => {
    const code = treeNode.value?.group_code;
    if (code === 'department') {
        return ['division', 'department'];
    }
    if (code === 'unit_section') {
        return ['division', 'department', 'unit_section'];
    }
    return [] as string[];
});

const allowsTopLevel = computed(() => {
    const code = treeNode.value?.group_code;
    return code === 'division' || code === 'unit_section';
});

const requiresParent = computed(() => !allowsTopLevel.value);

const parentOptions = computed(() => {
    if (!treeNode.value || !orgTree.value) {
        return [] as ParentOption[];
    }

    const excluded = new Set<number>([treeNode.value.id, ...collectDescendantIds(treeNode.value)]);
    const options: ParentOption[] = [];
    const allowed = new Set(allowedParentCodes.value);

    collectParentOptions(orgTree.value.nodes, [], excluded, allowed, options);

    return options;
});

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

watch(
    () => [props.open, props.node] as const,
    ([open, node]) => {
        if (!open || !node) {
            return;
        }

        form.clearErrors();
        form.parent_id = node.parent_id ?? '';
    },
    { immediate: true },
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
    excluded: Set<number>,
    allowed: Set<string>,
    options: ParentOption[],
): void {
    for (const node of nodes) {
        if (excluded.has(node.id)) {
            continue;
        }

        const path = [...ancestors, node.name];
        const typeLabel = node.group_name ?? node.group_code ?? '';

        if (node.group_code && allowed.has(node.group_code)) {
            options.push({
                id: node.id,
                label: `${path.join(' › ')}${typeLabel ? ` (${typeLabel})` : ''}`,
            });
        }

        if (node.children?.length) {
            collectParentOptions(node.children, path, excluded, allowed, options);
        }
    }
}

function submit() {
    if (!props.node?.id) {
        return;
    }

    form
        .transform((data) => ({
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
        :description="node ? `Re-parent “${node.name}” within the organization ladder.` : undefined"
        @close="emit('close')"
    >
        <form v-if="node" class="space-y-4" @submit.prevent="submit">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Type stays <span class="font-medium">{{ node.group_name ?? node.group_code }}</span>.
                Only valid parents for this type are listed.
            </p>

            <UiSelect
                v-model="form.parent_id"
                label="Parent subgroup"
                :error="form.errors.parent_id"
                :required="requiresParent"
            >
                <option v-if="!requiresParent" value="">Top level (under Strategic Leadership)</option>
                <option v-else value="" disabled>Select a parent…</option>
                <option v-for="option in parentOptions" :key="option.id" :value="option.id">
                    {{ option.label }}
                </option>
            </UiSelect>

            <div class="flex justify-end gap-2 pt-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" variant="primary" :disabled="form.processing">Move</UiButton>
            </div>
        </form>
    </UiModal>
</template>
