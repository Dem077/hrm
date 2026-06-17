<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { LeaveType } from '@/types/leave';

const props = defineProps<{
    leaveTypes: LeaveType[];
    emptyLeaveType: LeaveType;
    carryForwardEnabled: boolean;
}>();

const { can } = usePermissions();
const editingId = ref<number | null>(null);

const form = useForm({ ...props.emptyLeaveType });

function editLeaveType(leaveType: LeaveType) {
    editingId.value = leaveType.id;
    form.name = leaveType.name;
    form.code = leaveType.code ?? '';
    form.description = leaveType.description ?? '';
    form.requires_document = leaveType.requires_document;
    form.is_visible_to_employees = leaveType.is_visible_to_employees;
    form.is_active = leaveType.is_active;
    form.sort_order = leaveType.sort_order;
    form.annual_limit = leaveType.annual_limit;
    form.can_carry_forward = leaveType.can_carry_forward;
    form.max_carry_forward_days = leaveType.max_carry_forward_days;
}

function cancelEdit() {
    editingId.value = null;
    form.reset();
    form.defaults({ ...props.emptyLeaveType });
}

function submit() {
    if (editingId.value) {
        form.put(`/leave-types/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: cancelEdit,
        });
        return;
    }

    form.post('/leave-types', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.defaults({ ...props.emptyLeaveType });
        },
    });
}

function destroyLeaveType(id: number, name: string) {
    if (confirm(`Delete leave type "${name}"?`)) {
        router.delete(`/leave-types/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Leave Types" />

    <AppLayout>
        <PageHeader
            title="Leave types"
            description="Configure leave categories, whether a document is required, and whether employees can apply for them."
        />

        <UiCard padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Name</th>
                            <th class="px-5 py-3.5 font-medium">Code</th>
                            <th class="px-5 py-3.5 font-medium">Annual limit</th>
                            <th v-if="carryForwardEnabled" class="px-5 py-3.5 font-medium">Carry forward</th>
                            <th class="px-5 py-3.5 font-medium">Document</th>
                            <th class="px-5 py-3.5 font-medium">Visible to employees</th>
                            <th class="px-5 py-3.5 font-medium">Status</th>
                            <th class="px-5 py-3.5 font-medium">Requests</th>
                            <th class="px-5 py-3.5 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="leaveType in leaveTypes" :key="leaveType.id!">
                            <td class="px-5 py-4 font-medium text-slate-900 dark:text-white">{{ leaveType.name }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ leaveType.code ?? '—' }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                {{ leaveType.annual_limit ?? 'Unlimited' }}
                            </td>
                            <td v-if="carryForwardEnabled" class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                <template v-if="leaveType.can_carry_forward">
                                    Yes (max {{ leaveType.max_carry_forward_days ?? 0 }} days)
                                </template>
                                <template v-else>No</template>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ leaveType.requires_document ? 'Required' : 'Optional' }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ leaveType.is_visible_to_employees ? 'Yes' : 'HR only' }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        leaveType.is_active
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                    "
                                >
                                    {{ leaveType.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ leaveType.leave_requests_count ?? 0 }}</td>
                            <td class="px-5 py-4">
                                <div v-if="can('leave-types.update') || can('leave-types.delete')" class="flex gap-2">
                                    <UiButton v-if="can('leave-types.update')" size="sm" variant="ghost" @click="editLeaveType(leaveType)">Edit</UiButton>
                                    <UiButton
                                        v-if="can('leave-types.delete')"
                                        size="sm"
                                        variant="danger"
                                        @click="destroyLeaveType(leaveType.id!, leaveType.name)"
                                    >
                                        Delete
                                    </UiButton>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="leaveTypes.length === 0">
                            <td :colspan="carryForwardEnabled ? 9 : 8" class="px-5 py-12 text-center text-slate-500">
                                No leave types configured yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form
                v-if="can('leave-types.create') || can('leave-types.update')"
                class="space-y-4 border-t border-slate-100 p-5 dark:border-slate-800"
                @submit.prevent="submit"
            >
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ editingId ? 'Edit leave type' : 'Add leave type' }}
                </p>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
                    <UiInput v-model="form.code" label="Code" hint="Optional short code" :error="form.errors.code" />
                    <UiInput v-model="form.sort_order" label="Sort order" type="number" :error="form.errors.sort_order" />
                    <UiInput
                        v-model="form.annual_limit"
                        label="Annual limit (days)"
                        type="number"
                        hint="Leave blank for unlimited. Counted per leave year from joining date."
                        :error="form.errors.annual_limit"
                    />
                </div>
                <p v-if="!carryForwardEnabled" class="text-xs text-slate-500 dark:text-slate-400">
                    Carry-forward is disabled in global app settings.
                </p>
                <UiInput v-model="form.description" label="Description" :error="form.errors.description" />
                <div class="grid gap-3 md:grid-cols-3">
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                        <input v-model="form.requires_document" type="checkbox" class="rounded border-slate-300 text-brand-600 dark:border-slate-600" />
                        Supporting document required
                    </label>
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                        <input v-model="form.is_visible_to_employees" type="checkbox" class="rounded border-slate-300 text-brand-600 dark:border-slate-600" />
                        Visible to employees
                    </label>
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 dark:border-slate-600" />
                        Active
                    </label>
                    <label v-if="carryForwardEnabled" class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                        <input v-model="form.can_carry_forward" type="checkbox" class="rounded border-slate-300 text-brand-600 dark:border-slate-600" />
                        Can carry forward unused leave
                    </label>
                </div>
                <div v-if="carryForwardEnabled" class="grid gap-4 md:grid-cols-2">
                    <UiInput
                        v-model="form.max_carry_forward_days"
                        label="Max accumulated carry-forward days"
                        type="number"
                        hint="Required when carry forward is enabled."
                        :disabled="!form.can_carry_forward"
                        :error="form.errors.max_carry_forward_days"
                    />
                    <p v-if="form.errors.can_carry_forward" class="text-sm text-red-600 md:self-end">
                        {{ form.errors.can_carry_forward }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <UiButton type="submit" variant="primary" :disabled="form.processing">
                        {{ editingId ? 'Update leave type' : 'Add leave type' }}
                    </UiButton>
                    <UiButton v-if="editingId" type="button" variant="ghost" @click="cancelEdit">Cancel</UiButton>
                </div>
            </form>
        </UiCard>
    </AppLayout>
</template>
