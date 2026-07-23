<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import { formatPayrollMoney } from '@/lib/payroll';
import GradePackageModal from '@/pages/PayrollStructure/components/GradePackageModal.vue';
import type { LoanBankOption, PayrollComponent, StructureGradePackage } from '@/types/payroll';

const props = defineProps<{
    grades: StructureGradePackage[];
    components: PayrollComponent[];
    loanBanks: LoanBankOption[];
    canManage: boolean;
}>();

const modalOpen = ref(false);
const editingGrade = ref<StructureGradePackage | null>(null);
const search = ref('');
const expandedGroups = ref<Record<string, boolean>>({});
const expandedNodes = ref<Record<string, boolean>>({});
const expandedLevels = ref<Record<string, boolean>>({});

type LevelBucket = {
    key: string;
    levelNumber: number;
    referenceTitle: string;
    grades: StructureGradePackage[];
};

type NodeBucket = {
    key: string;
    name: string;
    levels: LevelBucket[];
    gradeCount: number;
};

type GroupBucket = {
    key: string;
    code: string;
    name: string;
    nodes: NodeBucket[];
    gradeCount: number;
};

const filteredGrades = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!query) {
        return props.grades;
    }

    return props.grades.filter((grade) => {
        const haystack = [
            grade.label,
            grade.grade,
            grade.title,
            grade.path_label,
            grade.group?.name,
            grade.node?.name,
            grade.level?.reference_title,
            grade.level ? `level ${grade.level.level_number}` : '',
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const grouped = computed((): GroupBucket[] => {
    const groupMap = new Map<string, GroupBucket>();

    for (const grade of filteredGrades.value) {
        const groupKey = String(grade.group?.id ?? grade.group?.code ?? 'unknown');
        const groupName = grade.group?.name ?? 'Unassigned';
        const groupCode = grade.group?.code ?? 'unknown';

        if (!groupMap.has(groupKey)) {
            groupMap.set(groupKey, {
                key: groupKey,
                code: groupCode,
                name: groupName,
                nodes: [],
                gradeCount: 0,
            });
        }

        const group = groupMap.get(groupKey)!;
        group.gradeCount += 1;

        const nodeKey = String(grade.node?.id ?? `${groupKey}-root`);
        const nodeName = grade.node?.name ?? 'Direct grades';

        let node = group.nodes.find((entry) => entry.key === nodeKey);
        if (!node) {
            node = { key: nodeKey, name: nodeName, levels: [], gradeCount: 0 };
            group.nodes.push(node);
        }
        node.gradeCount += 1;

        const levelKey = String(grade.level?.id ?? `${nodeKey}-level`);
        const levelNumber = grade.level?.level_number ?? 0;
        const referenceTitle = grade.level?.reference_title ?? 'Level';

        let level = node.levels.find((entry) => entry.key === levelKey);
        if (!level) {
            level = {
                key: levelKey,
                levelNumber,
                referenceTitle,
                grades: [],
            };
            node.levels.push(level);
        }

        level.grades.push(grade);
    }

    for (const group of groupMap.values()) {
        for (const node of group.nodes) {
            node.levels.sort((a, b) => b.levelNumber - a.levelNumber);
            for (const level of node.levels) {
                level.grades.sort((a, b) => {
                    if (a.sort_order !== b.sort_order) {
                        return a.sort_order - b.sort_order;
                    }
                    return a.grade.localeCompare(b.grade);
                });
            }
        }
        group.nodes.sort((a, b) => {
            if (a.key.endsWith('-root')) {
                return -1;
            }
            if (b.key.endsWith('-root')) {
                return 1;
            }
            return a.name.localeCompare(b.name);
        });
    }

    const order = ['strategic_leadership', 'division', 'department', 'unit_section'];

    return [...groupMap.values()].sort((a, b) => {
        const ai = order.indexOf(a.code);
        const bi = order.indexOf(b.code);
        return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
    });
});

const initialized = ref(false);

watch(
    grouped,
    (groups) => {
        if (initialized.value || groups.length === 0) {
            return;
        }

        initialized.value = true;
        for (const group of groups) {
            expandedGroups.value[group.key] = group.code === 'strategic_leadership' || group.code === 'department';
            for (const node of group.nodes.slice(0, 1)) {
                expandedNodes.value[node.key] = true;
                if (node.levels[0]) {
                    expandedLevels.value[node.levels[0].key] = true;
                }
            }
        }
    },
    { immediate: true },
);

watch(search, (value) => {
    if (!value.trim()) {
        return;
    }

    for (const group of grouped.value) {
        expandedGroups.value[group.key] = true;
        for (const node of group.nodes) {
            expandedNodes.value[node.key] = true;
            for (const level of node.levels) {
                expandedLevels.value[level.key] = true;
            }
        }
    }
});

function toggleGroup(key: string) {
    expandedGroups.value[key] = !expandedGroups.value[key];
}

function toggleNode(key: string) {
    expandedNodes.value[key] = !expandedNodes.value[key];
}

function toggleLevel(key: string) {
    expandedLevels.value[key] = !expandedLevels.value[key];
}

function expandAll() {
    for (const group of grouped.value) {
        expandedGroups.value[group.key] = true;
        for (const node of group.nodes) {
            expandedNodes.value[node.key] = true;
            for (const level of node.levels) {
                expandedLevels.value[level.key] = true;
            }
        }
    }
}

function collapseAll() {
    expandedGroups.value = {};
    expandedNodes.value = {};
    expandedLevels.value = {};
}

function isConfigured(grade: StructureGradePackage): boolean {
    return (grade.totals?.net ?? 0) !== 0 || grade.items.some((item) => Number(item.amount) > 0);
}

function openEdit(grade: StructureGradePackage) {
    editingGrade.value = grade;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editingGrade.value = null;
}
</script>

<template>
    <UiCard padding="none">
        <div class="space-y-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Designation salary structures</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Browse by company structure, then set salary amounts for each designation. Fixed net excludes daily lines until attendance is applied.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <UiButton size="sm" variant="ghost" @click="expandAll">Expand all</UiButton>
                    <UiButton size="sm" variant="ghost" @click="collapseAll">Collapse all</UiButton>
                </div>
            </div>

            <div class="max-w-md">
                <UiInput v-model="search" label="Search grades" placeholder="Division, department, level, grade, title…" />
            </div>
        </div>

        <div v-if="grades.length === 0" class="px-5 py-10 text-center text-sm text-slate-500">
            Create designations under Company Structure first, then configure salary structures here.
        </div>

        <div v-else-if="grouped.length === 0" class="px-5 py-10 text-center text-sm text-slate-500">
            No grades match “{{ search }}”.
        </div>

        <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
            <section v-for="group in grouped" :key="group.key">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 bg-slate-50 px-5 py-3.5 text-left hover:bg-slate-100/80 dark:bg-surface-elevated dark:hover:bg-slate-800/80"
                    @click="toggleGroup(group.key)"
                >
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-900 dark:text-white">
                            <span class="mr-2 text-slate-400">{{ expandedGroups[group.key] ? '▾' : '▸' }}</span>
                            {{ group.name }}
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ group.nodes.length }} {{ group.nodes.length === 1 ? 'unit' : 'units' }}
                            · {{ group.gradeCount }} {{ group.gradeCount === 1 ? 'grade' : 'grades' }}
                        </p>
                    </div>
                </button>

                <div v-if="expandedGroups[group.key]" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="node in group.nodes" :key="node.key" class="bg-white dark:bg-surface">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 px-5 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-900/40"
                            @click="toggleNode(node.key)"
                        >
                            <div class="min-w-0 pl-4">
                                <div class="font-medium text-slate-800 dark:text-slate-100">
                                    <span class="mr-2 text-slate-400">{{ expandedNodes[node.key] ? '▾' : '▸' }}</span>
                                    {{ node.name }}
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ node.levels.length }} {{ node.levels.length === 1 ? 'level' : 'levels' }}
                                    · {{ node.gradeCount }} {{ node.gradeCount === 1 ? 'grade' : 'grades' }}
                                </p>
                            </div>
                        </button>

                        <div v-if="expandedNodes[node.key]" class="space-y-3 px-5 pb-4 pl-9">
                            <div
                                v-for="level in node.levels"
                                :key="level.key"
                                class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 bg-slate-50/80 px-4 py-2.5 text-left dark:bg-slate-900/40"
                                    @click="toggleLevel(level.key)"
                                >
                                    <div class="font-medium text-slate-800 dark:text-slate-100">
                                        <span class="mr-2 text-slate-400">{{ expandedLevels[level.key] ? '▾' : '▸' }}</span>
                                        Level {{ level.levelNumber }}
                                        <span class="font-normal text-slate-500">· {{ level.referenceTitle }}</span>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ level.grades.length }} grades</span>
                                </button>

                                <div v-if="expandedLevels[level.key]" class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="border-y border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                            <tr>
                                                <th class="px-4 py-2.5 font-medium">Grade</th>
                                                <th class="px-4 py-2.5 font-medium">Title</th>
                                                <th class="px-4 py-2.5 font-medium">Fixed net</th>
                                                <th class="px-4 py-2.5 font-medium">Salary structure</th>
                                                <th v-if="canManage" class="px-4 py-2.5 font-medium">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            <tr v-for="grade in level.grades" :key="grade.id!">
                                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                                    {{ grade.grade }}
                                                </td>
                                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                                    {{ grade.title }}
                                                </td>
                                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                                    {{ formatPayrollMoney(grade.totals?.net ?? 0) }}
                                                    <span
                                                        v-if="grade.totals?.has_daily"
                                                        class="ml-1 text-xs font-normal text-slate-400"
                                                    >
                                                        + daily
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span
                                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                        :class="
                                                            isConfigured(grade)
                                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                                : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                                        "
                                                    >
                                                        {{ isConfigured(grade) ? 'Configured' : 'Needs amounts' }}
                                                    </span>
                                                </td>
                                                <td v-if="canManage" class="px-4 py-3">
                                                    <UiButton size="sm" variant="ghost" @click="openEdit(grade)">
                                                        Edit structure
                                                    </UiButton>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <GradePackageModal
            :open="modalOpen"
            :grade="editingGrade"
            :components="components"
            :loan-banks="loanBanks"
            @close="closeModal"
            @saved="closeModal"
        />
    </UiCard>
</template>
