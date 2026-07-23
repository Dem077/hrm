<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import { usePermissions } from '@/composables/usePermissions';

export type WorkflowStep = {
    key: string;
    label: string;
    description: string;
    enabled: boolean;
    locked: boolean;
};

export type WorkflowTarget = {
    id: number | null;
    name: string;
    description?: string;
    group_label?: string | null;
    leave: WorkflowStep[];
    overtime: WorkflowStep[];
};

export type ApprovalWorkflowsPayload = {
    default: WorkflowTarget;
    branches: WorkflowTarget[];
};

const props = defineProps<{
    approvalWorkflows: ApprovalWorkflowsPayload;
}>();

const { can } = usePermissions();
const canEdit = computed(() => can('attendance-settings.leave-workflow.update'));

type Kind = 'leave' | 'overtime';

const selectedTargetId = ref<number | null>(null);
const selectedKind = ref<Kind>('leave');

const targets = computed<WorkflowTarget[]>(() => [
    props.approvalWorkflows.default,
    ...props.approvalWorkflows.branches,
]);

const selectedTarget = computed(
    () => targets.value.find((target) => target.id === selectedTargetId.value) ?? props.approvalWorkflows.default,
);

const form = useForm({
    kind: 'leave' as Kind,
    structure_node_id: null as number | null,
    steps: [] as Array<{
        key: string;
        enabled: boolean;
        label: string;
        description: string;
        locked: boolean;
    }>,
});

function loadForm() {
    const target = selectedTarget.value;
    const steps = selectedKind.value === 'leave' ? target.leave : target.overtime;

    form.clearErrors();
    form.kind = selectedKind.value;
    form.structure_node_id = target.id;
    form.steps = steps.map((step) => ({ ...step }));
}

watch([selectedTargetId, selectedKind], loadForm, { immediate: true });

watch(
    () => props.approvalWorkflows,
    () => loadForm(),
    { deep: true },
);

function moveStep(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= form.steps.length) {
        return;
    }

    if (form.steps[index]?.locked || form.steps[target]?.locked) {
        return;
    }

    const steps = [...form.steps];
    const [item] = steps.splice(index, 1);
    steps.splice(target, 0, item);
    form.steps = steps;
}

function submit() {
    form
        .transform((data) => ({
            kind: data.kind,
            structure_node_id: data.structure_node_id,
            steps: data.steps
                .filter((step) => step.key !== 'hr')
                .map((step) => ({
                    key: step.key,
                    enabled: step.enabled,
                })),
        }))
        .put('/attendance-settings/approval-workflows', {
            preserveScroll: true,
        });
}
</script>

<template>
    <UiCard
        title="Approval workflows by branch"
        description="Configure separate leave and overtime approval chains for each branch directly under Strategic Leadership. Employees follow their branch’s flow; Strategic Leadership staff use the company default."
    >
        <div class="space-y-5">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="target in targets"
                    :key="String(target.id)"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        selectedTargetId === target.id
                            ? 'bg-brand-600 text-white'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-surface-elevated dark:text-slate-300 dark:hover:bg-slate-700'
                    "
                    @click="selectedTargetId = target.id"
                >
                    {{ target.name }}
                </button>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
                <p class="font-medium text-slate-800 dark:text-slate-100">
                    {{ selectedTarget.name }}
                    <span v-if="selectedTarget.group_label" class="font-normal text-slate-500">
                        · {{ selectedTarget.group_label }}
                    </span>
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{
                        selectedTarget.description ??
                        'Applies to employees whose designation sits under this branch in the company structure.'
                    }}
                </p>
            </div>

            <div class="inline-flex gap-1 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-surface-muted">
                <button
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition"
                    :class="
                        selectedKind === 'leave'
                            ? 'bg-brand-600 text-white'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                    "
                    @click="selectedKind = 'leave'"
                >
                    Leave
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium transition"
                    :class="
                        selectedKind === 'overtime'
                            ? 'bg-brand-600 text-white'
                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                    "
                    @click="selectedKind = 'overtime'"
                >
                    Overtime
                </button>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <ol class="space-y-2">
                    <li
                        v-for="(step, index) in form.steps"
                        :key="step.key"
                        class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <label class="flex min-w-0 flex-1 items-start gap-3">
                                <input
                                    v-model="step.enabled"
                                    type="checkbox"
                                    class="mt-1 rounded border-slate-300"
                                    :disabled="step.locked || !canEdit"
                                />
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">
                                        {{ index + 1 }}. {{ step.label }}
                                    </span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ step.description }}</span>
                                </span>
                            </label>
                            <div v-if="!step.locked && canEdit" class="flex gap-1">
                                <UiButton type="button" size="sm" variant="ghost" :disabled="index === 0" @click="moveStep(index, -1)">
                                    Up
                                </UiButton>
                                <UiButton
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    :disabled="index >= form.steps.filter((item) => !item.locked).length - 1"
                                    @click="moveStep(index, 1)"
                                >
                                    Down
                                </UiButton>
                            </div>
                        </div>
                    </li>
                </ol>

                <p v-if="form.errors.steps || form.errors.kind || form.errors.structure_node_id" class="text-xs text-red-600 dark:text-red-400">
                    {{ form.errors.steps || form.errors.kind || form.errors.structure_node_id }}
                </p>

                <div v-if="canEdit" class="flex justify-end">
                    <UiButton type="submit" variant="primary" :disabled="form.processing">
                        Save {{ selectedKind === 'leave' ? 'leave' : 'overtime' }} workflow
                    </UiButton>
                </div>
            </form>
        </div>
    </UiCard>
</template>
