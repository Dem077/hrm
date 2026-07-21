<script setup lang="ts">
import { computed, ref } from 'vue';

import type { StructureLevel } from '@/types/companyStructure';

export type ChartNode = {
    key: string;
    name: string;
    code?: string | null;
    typeLabel: string;
    typeCode: string;
    headName?: string | null;
    isActive?: boolean;
    levels: StructureLevel[];
    children: ChartNode[];
};

const props = defineProps<{
    node: ChartNode;
    showGrades: boolean;
    depth?: number;
}>();

const collapsed = ref(false);

const gradeCount = computed(() =>
    props.node.levels.reduce((sum, level) => sum + (level.grades?.length ?? 0), 0),
);

const typeTone = computed(() => {
    switch (props.node.typeCode) {
        case 'strategic_leadership':
            return 'border-brand-500/50 bg-amber-50 text-amber-950 dark:border-brand-400/40 dark:bg-amber-950/30 dark:text-amber-50';
        case 'division':
            return 'border-sky-400/50 bg-sky-50 text-sky-900 dark:border-sky-500/30 dark:bg-sky-950/40 dark:text-sky-100';
        case 'department':
            return 'border-emerald-400/50 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-100';
        case 'unit_section':
            return 'border-amber-400/50 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-100';
        default:
            return 'border-slate-300 bg-white text-slate-900 dark:border-slate-700 dark:bg-surface dark:text-white';
    }
});

function toggle() {
    if (props.node.children.length === 0) {
        return;
    }
    collapsed.value = !collapsed.value;
}
</script>

<template>
    <li class="org-chart-node">
        <div class="org-chart-card-wrap">
            <button
                type="button"
                class="org-chart-card w-56 rounded-xl border px-3 py-2.5 text-left shadow-sm transition hover:shadow-md"
                :class="typeTone"
                @click="toggle"
            >
                <div class="flex items-start justify-between gap-2">
                    <span
                        class="rounded-md bg-white/70 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-black/20 dark:text-slate-200"
                    >
                        {{ node.typeLabel }}
                    </span>
                    <span
                        v-if="node.children.length"
                        class="text-xs text-slate-500 dark:text-slate-300"
                        :title="collapsed ? 'Expand' : 'Collapse'"
                    >
                        {{ collapsed ? '▸' : '▾' }}
                    </span>
                </div>
                <p class="mt-1.5 text-sm font-semibold leading-snug">{{ node.name }}</p>
                <p v-if="node.code" class="mt-0.5 text-[11px] opacity-70">{{ node.code }}</p>
                <p class="mt-1 text-[11px] opacity-80">
                    Head grades: {{ node.headName ?? '—' }}
                    <span v-if="gradeCount"> · {{ gradeCount }} grade{{ gradeCount === 1 ? '' : 's' }}</span>
                </p>

                <div v-if="showGrades && node.levels.length" class="mt-2 space-y-1 border-t border-black/10 pt-2 dark:border-white/10">
                    <div v-for="level in node.levels" :key="level.id" class="text-[10px] leading-snug opacity-90">
                        <span class="font-medium">L{{ level.level_number }}</span>
                        <span v-if="level.reference_title"> · {{ level.reference_title }}</span>
                        <ul v-if="level.grades.length" class="mt-0.5 space-y-0.5 pl-2">
                            <li v-for="grade in level.grades" :key="grade.id" class="opacity-80">
                                {{ grade.label }}
                            </li>
                        </ul>
                    </div>
                </div>
            </button>
        </div>

        <ul v-if="node.children.length && !collapsed" class="org-chart-children">
            <OrgChartBranch
                v-for="child in node.children"
                :key="child.key"
                :node="child"
                :show-grades="showGrades"
                :depth="(depth ?? 0) + 1"
            />
        </ul>
    </li>
</template>

<script lang="ts">
export default {
    name: 'OrgChartBranch',
};
</script>

<style scoped>
.org-chart-node {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-inline: 0.75rem;
}

.org-chart-card-wrap {
    position: relative;
    z-index: 1;
}

.org-chart-children {
    position: relative;
    display: flex;
    justify-content: center;
    padding-top: 1.5rem;
    margin: 0;
    list-style: none;
}

.org-chart-children::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 1px;
    height: 1.5rem;
    background: rgb(148 163 184);
}

:global(.dark) .org-chart-children::before {
    background: rgb(71 85 105);
}

.org-chart-children > .org-chart-node {
    position: relative;
    padding-top: 1.5rem;
}

.org-chart-children > .org-chart-node::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 1px;
    height: 1.5rem;
    background: rgb(148 163 184);
}

:global(.dark) .org-chart-children > .org-chart-node::before {
    background: rgb(71 85 105);
}

.org-chart-children > .org-chart-node::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: rgb(148 163 184);
}

:global(.dark) .org-chart-children > .org-chart-node::after {
    background: rgb(71 85 105);
}

.org-chart-children > .org-chart-node:first-child::after {
    left: 50%;
}

.org-chart-children > .org-chart-node:last-child::after {
    right: 50%;
}

.org-chart-children > .org-chart-node:only-child::after {
    display: none;
}
</style>
