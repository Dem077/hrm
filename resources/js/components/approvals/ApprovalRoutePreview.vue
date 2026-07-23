<script setup lang="ts">
defineProps<{
    preview: {
        first_approver: { id: number; name: string; staff_id: string } | null;
        goes_directly_to_hr: boolean;
        template?: {
            id: number;
            name: string;
            source: string;
            source_node: { id: number; name: string } | null;
        } | null;
        steps: Array<{
            key: string;
            label: string;
            status: string;
            approver: { id: number; name: string; staff_id: string } | null;
            skip_reason: string | null;
        }>;
        hint: string | null;
    } | null;
    fallbackApprover?: { id: number; name: string; staff_id: string } | null;
}>();
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
        <p class="font-medium text-slate-700 dark:text-slate-300">Approval route</p>

        <template v-if="preview">
            <p v-if="preview.template" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Template:
                <span class="font-medium text-slate-700 dark:text-slate-200">{{ preview.template.name }}</span>
                <template v-if="preview.template.source_node">
                    (from {{ preview.template.source_node.name }})
                </template>
                <template v-else-if="preview.template.source === 'company_default'">
                    (company default)
                </template>
            </p>

            <p class="mt-1 text-slate-600 dark:text-slate-400">
                <template v-if="preview.first_approver">
                    First structure approver:
                    <span class="font-medium text-slate-800 dark:text-slate-100">
                        {{ preview.first_approver.name }} ({{ preview.first_approver.staff_id }})
                    </span>
                </template>
                <template v-else>
                    No structure approver found — request will go directly to HR.
                </template>
            </p>

            <ul class="mt-3 space-y-2 border-t border-slate-200 pt-3 dark:border-slate-700">
                <li
                    v-for="step in preview.steps"
                    :key="step.key"
                    class="flex gap-2 text-xs"
                >
                    <span
                        class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
                        :class="step.status === 'pending' ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"
                    />
                    <span class="min-w-0">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ step.label }}</span>
                        <template v-if="step.approver">
                            — {{ step.approver.name }}
                        </template>
                        <template v-else-if="step.key === 'hr'">
                            — final approval
                        </template>
                        <template v-else-if="step.skip_reason">
                            <span class="text-amber-700 dark:text-amber-300"> — skipped: {{ step.skip_reason }}</span>
                        </template>
                    </span>
                </li>
            </ul>

            <p v-if="preview.hint" class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                {{ preview.hint }}
            </p>
        </template>

        <template v-else>
            <p class="mt-1 text-slate-600 dark:text-slate-400">
                {{
                    fallbackApprover
                        ? `${fallbackApprover.name} (${fallbackApprover.staff_id})`
                        : 'No structure approver found — request will go directly to HR'
                }}
            </p>
        </template>

        <p class="mt-2 text-xs text-slate-500">
            After structure approvals, HR always gives the final decision.
        </p>
    </div>
</template>
