<script setup lang="ts">
import { router } from '@inertiajs/vue3';

import UiActionMenu from '@/components/ui/UiActionMenu.vue';
import UiActionMenuItem from '@/components/ui/UiActionMenuItem.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiIconButton from '@/components/ui/UiIconButton.vue';
import type { StructureGrade, StructureLevel } from '@/types/companyStructure';

defineProps<{
    levels: StructureLevel[];
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
}>();

const emit = defineEmits<{
    addLevel: [];
    editLevel: [level: StructureLevel];
    addGrade: [level: StructureLevel];
    editGrade: [grade: StructureGrade, level: StructureLevel];
}>();

function deleteLevel(level: StructureLevel) {
    if (!confirm(`Delete Level ${level.level_number} (${level.reference_title})?`)) {
        return;
    }
    router.delete(`/company-structure/levels/${level.id}`, { preserveScroll: true });
}

function deleteGrade(grade: StructureGrade) {
    if (!confirm(`Delete ${grade.label}?`)) {
        return;
    }
    router.delete(`/company-structure/grades/${grade.id}`, { preserveScroll: true });
}

function moveLevel(levels: StructureLevel[], index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= levels.length) {
        return;
    }
    const ordered = levels.map((level) => level.id);
    const [item] = ordered.splice(index, 1);
    ordered.splice(target, 0, item);
    const current = levels[index];
    router.post(
        '/company-structure/levels/reorder',
        {
            ordered_ids: ordered,
            structure_group_id: current.structure_group_id,
            structure_node_id: current.structure_node_id,
        },
        { preserveScroll: true },
    );
}

function moveGrade(level: StructureLevel, index: number, direction: -1 | 1) {
    const grades = level.grades;
    const target = index + direction;
    if (target < 0 || target >= grades.length) {
        return;
    }
    const ordered = grades.map((grade) => grade.id);
    const [item] = ordered.splice(index, 1);
    ordered.splice(target, 0, item);
    router.post(`/company-structure/levels/${level.id}/grades/reorder`, { ordered_ids: ordered }, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Levels</h4>
            <UiButton v-if="canCreate" size="sm" variant="ghost" @click="emit('addLevel')">Add level</UiButton>
        </div>

        <p v-if="levels.length === 0" class="text-sm text-slate-500">No levels yet.</p>

        <div
            v-for="(level, levelIndex) in levels"
            :key="level.id"
            class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-900/40"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="font-medium text-slate-900 dark:text-white">
                        Level {{ level.level_number }}
                        <span class="font-normal text-slate-500">· {{ level.reference_title }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-0.5">
                    <template v-if="canUpdate">
                        <UiIconButton
                            label="Move up"
                            :disabled="levelIndex === 0"
                            @click="moveLevel(levels, levelIndex, -1)"
                        >
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
                            :disabled="levelIndex === levels.length - 1"
                            @click="moveLevel(levels, levelIndex, 1)"
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

                    <UiButton v-if="canCreate" size="sm" variant="ghost" @click="emit('addGrade', level)">
                        Add designation
                    </UiButton>

                    <UiActionMenu v-if="canUpdate || canDelete" label="More">
                        <UiActionMenuItem v-if="canUpdate" @click="emit('editLevel', level)">Edit</UiActionMenuItem>
                        <UiActionMenuItem v-if="canDelete" danger @click="deleteLevel(level)">Delete</UiActionMenuItem>
                    </UiActionMenu>
                </div>
            </div>

            <ul class="mt-2 space-y-1.5">
                <li
                    v-for="(grade, gradeIndex) in level.grades"
                    :key="grade.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-white px-2.5 py-1.5 text-sm dark:bg-surface"
                >
                    <div>
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ grade.label }}</span>
                        <span
                            class="ml-2 text-xs"
                            :class="grade.is_active ? 'text-emerald-600' : 'text-slate-400'"
                        >
                            {{ grade.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-0.5">
                        <template v-if="canUpdate">
                            <UiIconButton
                                label="Move up"
                                :disabled="gradeIndex === 0"
                                @click="moveGrade(level, gradeIndex, -1)"
                            >
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
                                :disabled="gradeIndex === level.grades.length - 1"
                                @click="moveGrade(level, gradeIndex, 1)"
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

                        <UiActionMenu v-if="canUpdate || canDelete" label="More">
                            <UiActionMenuItem v-if="canUpdate" @click="emit('editGrade', grade, level)">
                                Edit
                            </UiActionMenuItem>
                            <UiActionMenuItem v-if="canDelete" danger @click="deleteGrade(grade)">
                                Delete
                            </UiActionMenuItem>
                        </UiActionMenu>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
