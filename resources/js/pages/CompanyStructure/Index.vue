<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import ImportPreviewModal from '@/pages/CompanyStructure/components/ImportPreviewModal.vue';
import type { ImportPreview } from '@/pages/CompanyStructure/components/ImportPreviewModal.vue';
import LevelsPanel from '@/pages/CompanyStructure/components/LevelsPanel.vue';
import MoveNodeModal from '@/pages/CompanyStructure/components/MoveNodeModal.vue';
import NodeTree from '@/pages/CompanyStructure/components/NodeTree.vue';
import StructureFormModal from '@/pages/CompanyStructure/components/StructureFormModal.vue';
import type {
    StructureGrade,
    StructureGroup,
    StructureHeadOption,
    StructureLevel,
    StructureNode,
} from '@/types/companyStructure';

const props = defineProps<{
    groups: StructureGroup[];
    headOptions: StructureHeadOption[];
    importPreview?: ImportPreview | null;
    importFileName?: string | null;
}>();

const { can } = usePermissions();
const canCreate = can('company-structure.create');
const canUpdate = can('company-structure.update');
const canDelete = can('company-structure.delete');

const fileInput = ref<HTMLInputElement | null>(null);
const importForm = useForm<{ file: File | null }>({
    file: null,
});

const previewOpen = computed(() => Boolean(props.importPreview));

function openImportPicker() {
    fileInput.value?.click();
}

function onImportFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (!file) {
        return;
    }

    importForm.file = file;
    importForm.post('/company-structure/import/preview', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importForm.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

const modalOpen = ref(false);
const modalMode = ref<'node' | 'level' | 'grade'>('node');
const modalTitle = ref('');
const modalGroupId = ref<number | null>(null);
const modalParentId = ref<number | null>(null);
const modalNodeId = ref<number | null>(null);
const modalLevelId = ref<number | null>(null);
const modalNode = ref<StructureNode | null>(null);
const modalLevel = ref<StructureLevel | null>(null);
const modalGrade = ref<StructureGrade | null>(null);

const moveModalOpen = ref(false);
const movingNode = ref<StructureNode | null>(null);

function closeModal() {
    modalOpen.value = false;
    modalNode.value = null;
    modalLevel.value = null;
    modalGrade.value = null;
}

function closeMoveModal() {
    moveModalOpen.value = false;
    movingNode.value = null;
}

function openAddNode(groupId: number, parentId: number | null = null) {
    modalMode.value = 'node';
    modalTitle.value = parentId ? 'Add child subgroup' : 'Add subgroup';
    modalGroupId.value = groupId;
    modalParentId.value = parentId;
    modalNode.value = null;
    modalOpen.value = true;
}

function openEditNode(node: StructureNode) {
    modalMode.value = 'node';
    modalTitle.value = 'Edit subgroup';
    modalGroupId.value = node.structure_group_id;
    modalNode.value = node;
    modalOpen.value = true;
}

function openMoveNode(node: StructureNode) {
    movingNode.value = node;
    moveModalOpen.value = true;
}

function openAddLevel(owner: { groupId?: number | null; node?: StructureNode | null }) {
    modalMode.value = 'level';
    modalTitle.value = 'Add level';
    modalGroupId.value = owner.groupId ?? null;
    modalNodeId.value = owner.node?.id ?? null;
    modalLevel.value = null;
    modalOpen.value = true;
}

function openEditLevel(level: StructureLevel) {
    modalMode.value = 'level';
    modalTitle.value = 'Edit level';
    modalLevel.value = level;
    modalGroupId.value = level.structure_group_id;
    modalNodeId.value = level.structure_node_id;
    modalOpen.value = true;
}

function openAddGrade(level: StructureLevel) {
    modalMode.value = 'grade';
    modalTitle.value = 'Add grade';
    modalLevelId.value = level.id;
    modalGrade.value = null;
    modalOpen.value = true;
}

function openEditGrade(grade: StructureGrade, level: StructureLevel) {
    modalMode.value = 'grade';
    modalTitle.value = 'Edit grade';
    modalLevelId.value = level.id;
    modalGrade.value = grade;
    modalOpen.value = true;
}
</script>

<template>
    <Head title="Company Structure" />

    <AppLayout>
        <PageHeader
            title="Company Structure"
            description="Manage Strategic Leadership, Divisions, Departments, and Units with levels and grades."
        >
            <template #actions>
                <UiButton href="/company-structure/sample-csv" external variant="secondary" size="sm">
                    Download sample CSV
                </UiButton>
                <UiButton
                    v-if="canCreate"
                    type="button"
                    variant="primary"
                    size="sm"
                    :disabled="importForm.processing"
                    @click="openImportPicker"
                >
                    {{ importForm.processing ? 'Preparing…' : 'Import CSV' }}
                </UiButton>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".csv,text/csv"
                    class="hidden"
                    @change="onImportFileChange"
                />
            </template>
        </PageHeader>

        <p v-if="importForm.errors.file" class="mb-4 text-sm text-red-600">{{ importForm.errors.file }}</p>

        <div class="space-y-6">
            <UiCard v-for="group in groups" :key="group.id" :title="group.name" padding="md">
                <template #actions>
                    <UiButton
                        v-if="canCreate && group.allows_nodes"
                        size="sm"
                        variant="secondary"
                        @click="openAddNode(group.id)"
                    >
                        Add subgroup
                    </UiButton>
                    <UiButton
                        v-else-if="canCreate && !group.allows_nodes"
                        size="sm"
                        variant="secondary"
                        @click="openAddLevel({ groupId: group.id })"
                    >
                        Add level
                    </UiButton>
                </template>

                <div v-if="!group.allows_nodes">
                    <LevelsPanel
                        :levels="group.levels"
                        :can-create="canCreate"
                        :can-update="canUpdate"
                        :can-delete="canDelete"
                        @add-level="openAddLevel({ groupId: group.id })"
                        @edit-level="openEditLevel"
                        @add-grade="openAddGrade"
                        @edit-grade="openEditGrade"
                    />
                </div>

                <div v-else>
                    <p v-if="group.nodes.length === 0" class="text-sm text-slate-500">No subgroups yet.</p>
                    <NodeTree
                        v-else
                        :nodes="group.nodes"
                        :group-id="group.id"
                        :can-create="canCreate"
                        :can-update="canUpdate"
                        :can-delete="canDelete"
                        @add-node="(parentId) => openAddNode(group.id, parentId)"
                        @edit-node="openEditNode"
                        @move-node="openMoveNode"
                        @add-level="(node) => openAddLevel({ node })"
                        @edit-level="openEditLevel"
                        @add-grade="openAddGrade"
                        @edit-grade="openEditGrade"
                    />
                </div>
            </UiCard>
        </div>

        <StructureFormModal
            :open="modalOpen"
            :mode="modalMode"
            :title="modalTitle"
            :group-id="modalGroupId"
            :parent-id="modalParentId"
            :node-id="modalNodeId"
            :level-id="modalLevelId"
            :node="modalNode"
            :level="modalLevel"
            :grade="modalGrade"
            :head-options="headOptions"
            @close="closeModal"
        />

        <MoveNodeModal
            :open="moveModalOpen"
            :node="movingNode"
            :groups="props.groups"
            @close="closeMoveModal"
        />

        <ImportPreviewModal
            :open="previewOpen"
            :preview="props.importPreview ?? null"
            :file-name="props.importFileName"
            @close="() => {}"
        />
    </AppLayout>
</template>
