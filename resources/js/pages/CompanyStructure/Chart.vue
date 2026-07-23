<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import OrgChartBranch, { type ChartNode } from '@/pages/CompanyStructure/components/OrgChartBranch.vue';
import type { StructureGroup, StructureNode } from '@/types/companyStructure';

const props = defineProps<{
    groups: StructureGroup[];
}>();

const showGrades = ref(true);
const scale = ref(1);
const panX = ref(0);
const panY = ref(0);
const isPanning = ref(false);
const viewportRef = ref<HTMLElement | null>(null);

const MIN_SCALE = 0.5;
const MAX_SCALE = 1.5;
const DRAG_THRESHOLD = 5;
const WHEEL_STEP = 0.1;

let pointerActive = false;
let suppressClick = false;
let activePointerId: number | null = null;
let dragStartX = 0;
let dragStartY = 0;
let panOriginX = 0;
let panOriginY = 0;

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
        heads: node.heads ?? [],
        headGrades: node.head_grades ?? [],
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
        heads: [],
        headGrades: [],
        levels: strategicGroup.value?.levels ?? [],
        children,
    };
});

const chartTransform = computed(
    () => `translate(calc(-50% + ${panX.value}px), ${panY.value}px) scale(${scale.value})`,
);

function clampScale(value: number): number {
    return Math.min(MAX_SCALE, Math.max(MIN_SCALE, Math.round(value * 10) / 10));
}

function setScaleAtPoint(nextScale: number, pointX: number, pointY: number) {
    const clamped = clampScale(nextScale);

    if (clamped === scale.value) {
        return;
    }

    const ratio = clamped / scale.value;
    panX.value = pointX - (pointX - panX.value) * ratio;
    panY.value = pointY - (pointY - panY.value) * ratio;
    scale.value = clamped;
}

function zoomIn() {
    scale.value = clampScale(scale.value + WHEEL_STEP);
}

function zoomOut() {
    scale.value = clampScale(scale.value - WHEEL_STEP);
}

function resetView() {
    scale.value = 1;
    panX.value = 0;
    panY.value = 0;
}

function onWheel(event: WheelEvent) {
    event.preventDefault();

    const viewport = viewportRef.value;
    if (!viewport) {
        return;
    }

    const rect = viewport.getBoundingClientRect();
    // Match transform origin: horizontal center, top-8 (2rem) padding.
    const pointX = event.clientX - rect.left - rect.width / 2;
    const pointY = event.clientY - rect.top - 32;
    const direction = event.deltaY > 0 ? -WHEEL_STEP : WHEEL_STEP;

    setScaleAtPoint(scale.value + direction, pointX, pointY);
}

function detachWindowListeners() {
    window.removeEventListener('pointermove', onWindowPointerMove);
    window.removeEventListener('pointerup', onWindowPointerUp);
    window.removeEventListener('pointercancel', onWindowPointerUp);
}

function endPointerInteraction() {
    pointerActive = false;
    activePointerId = null;
    isPanning.value = false;
    detachWindowListeners();
}

function onPointerDown(event: PointerEvent) {
    if (event.button !== 0) {
        return;
    }

    pointerActive = true;
    suppressClick = false;
    isPanning.value = false;
    activePointerId = event.pointerId;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    panOriginX = panX.value;
    panOriginY = panY.value;

    window.addEventListener('pointermove', onWindowPointerMove);
    window.addEventListener('pointerup', onWindowPointerUp);
    window.addEventListener('pointercancel', onWindowPointerUp);
}

function onWindowPointerMove(event: PointerEvent) {
    if (!pointerActive || event.pointerId !== activePointerId) {
        return;
    }

    const dx = event.clientX - dragStartX;
    const dy = event.clientY - dragStartY;

    if (!isPanning.value) {
        if (Math.abs(dx) < DRAG_THRESHOLD && Math.abs(dy) < DRAG_THRESHOLD) {
            return;
        }

        isPanning.value = true;
        suppressClick = true;
    }

    panX.value = panOriginX + dx;
    panY.value = panOriginY + dy;
}

function onWindowPointerUp(event: PointerEvent) {
    if (event.pointerId !== activePointerId) {
        return;
    }

    endPointerInteraction();
}

function onClickCapture(event: MouseEvent) {
    if (!suppressClick) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    suppressClick = false;
}

onUnmounted(() => {
    endPointerInteraction();
});
</script>

<template>
    <Head title="Organization Chart" />

    <AppLayout>
        <PageHeader
            title="Organization Chart"
            description="Drag to pan, scroll to zoom. Strategic Leadership → Divisions → Departments → Units / Sections."
        >
            <template #actions>
                <UiButton href="/company-structure" variant="secondary" size="sm">Manage structure</UiButton>
                <UiButton type="button" variant="secondary" size="sm" @click="showGrades = !showGrades">
                    {{ showGrades ? 'Hide designations' : 'Show designations' }}
                </UiButton>
                <UiButton type="button" variant="ghost" size="sm" @click="zoomOut">−</UiButton>
                <UiButton type="button" variant="ghost" size="sm" @click="resetView">
                    {{ Math.round(scale * 100) }}%
                </UiButton>
                <UiButton type="button" variant="ghost" size="sm" @click="zoomIn">+</UiButton>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-amber-500" /> Strategic Leadership
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-sky-500" /> Division
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" /> Department
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-sm bg-violet-500" /> Unit / Section
            </span>
            <span class="ml-auto text-slate-400 dark:text-slate-500">Drag to move · scroll to zoom</span>
        </div>

        <UiCard padding="none">
            <div
                v-if="!chartRoot"
                class="px-6 py-16 text-center text-sm text-slate-500"
            >
                No organization structure to display yet.
                <a href="/company-structure" class="ml-1 text-brand-600 hover:underline dark:text-brand-400">Add structure</a>
            </div>

            <div
                v-else
                ref="viewportRef"
                class="relative h-[min(75vh,760px)] touch-none overflow-hidden select-none"
                :class="isPanning ? 'cursor-grabbing' : 'cursor-grab'"
                @pointerdown="onPointerDown"
                @wheel.prevent="onWheel"
                @click.capture="onClickCapture"
            >
                <div
                    class="absolute left-1/2 top-8 origin-top will-change-transform"
                    :style="{ transform: chartTransform }"
                >
                    <ul class="org-chart-root flex list-none justify-center p-0">
                        <OrgChartBranch :node="chartRoot" :show-grades="showGrades" />
                    </ul>
                </div>
            </div>
        </UiCard>
    </AppLayout>
</template>
