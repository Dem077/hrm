<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import OrgChartBranch, { type ChartNode } from '@/pages/CompanyStructure/components/OrgChartBranch.vue';
import type { StructureGroup, StructureNode } from '@/types/companyStructure';

const props = defineProps<{
    groups: StructureGroup[];
}>();

const showGrades = ref(false);
const scale = ref(1);

const strategicGroup = computed(() => props.groups.find((group) => group.code === 'strategic_leadership') ?? null);
const organizationGroup = computed(
    () => props.groups.find((group) => group.is_org_tree || group.code === 'organization') ?? null,
);

function structureNodeToChartNode(node: StructureNode): ChartNode {
    return {
        key: `node-${node.id}`,
        name: node.name,
        code: node.code,
        typeLabel: node.group_name ?? node.group_code ?? 'Node',
        typeCode: node.group_code ?? 'node',
        headName: node.head_grades?.length
            ? node.head_grades.map((grade) => grade.label).join(', ')
            : null,
        isActive: node.is_active,
        levels: node.levels ?? [],
        children: (node.children ?? []).map(structureNodeToChartNode),
    };
}

const chartRoot = computed<ChartNode | null>(() => {
    const children = (organizationGroup.value?.nodes ?? []).map(structureNodeToChartNode);

    if (!strategicGroup.value && children.length === 0) {
        return null;
    }

    return {
        key: 'strategic-leadership',
        name: strategicGroup.value?.name ?? 'Strategic Leadership',
        typeLabel: 'Strategic Leadership',
        typeCode: 'strategic_leadership',
        headName: null,
        levels: strategicGroup.value?.levels ?? [],
        children,
    };
});

function zoomIn() {
    scale.value = Math.min(1.5, Math.round((scale.value + 0.1) * 10) / 10);
}

function zoomOut() {
    scale.value = Math.max(0.5, Math.round((scale.value - 0.1) * 10) / 10);
}

function resetZoom() {
    scale.value = 1;
}
</script>

<template>
    <Head title="Organization Chart" />

    <AppLayout>
        <PageHeader
            title="Organization Chart"
            description="Visual view of Strategic Leadership → Divisions → Departments → Units / Sections."
        >
            <template #actions>
                <UiButton href="/company-structure" variant="secondary" size="sm">Manage structure</UiButton>
                <UiButton type="button" variant="secondary" size="sm" @click="showGrades = !showGrades">
                    {{ showGrades ? 'Hide grades' : 'Show grades' }}
                </UiButton>
                <UiButton type="button" variant="ghost" size="sm" @click="zoomOut">−</UiButton>
                <UiButton type="button" variant="ghost" size="sm" @click="resetZoom">{{ Math.round(scale * 100) }}%</UiButton>
                <UiButton type="button" variant="ghost" size="sm" @click="zoomIn">+</UiButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-brand-500" /> Strategic Leadership
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-sky-500" /> Division
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" /> Department
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-amber-500" /> Unit / Section
            </span>
        </div>

        <UiCard padding="none">
            <div
                v-if="!chartRoot"
                class="px-6 py-16 text-center text-sm text-slate-500"
            >
                No organization structure to display yet.
                <a href="/company-structure" class="ml-1 text-brand-600 hover:underline dark:text-brand-400">Add structure</a>
            </div>

            <div v-else class="overflow-auto px-4 py-8">
                <div
                    class="inline-block min-w-full origin-top transition-transform duration-150"
                    :style="{ transform: `scale(${scale})` }"
                >
                    <ul class="org-chart-root mx-auto flex list-none justify-center p-0">
                        <OrgChartBranch :node="chartRoot" :show-grades="showGrades" />
                    </ul>
                </div>
            </div>
        </UiCard>
    </AppLayout>
</template>
