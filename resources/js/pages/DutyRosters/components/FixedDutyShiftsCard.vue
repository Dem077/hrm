<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { DutyShiftTemplate } from '@/types/hrm';

const props = defineProps<{
    templates: DutyShiftTemplate[];
    emptyTemplate: DutyShiftTemplate;
}>();

const { can } = usePermissions();

const modalOpen = ref(false);
const editingTemplate = ref<DutyShiftTemplate | null>(null);
const templateForm = useForm({ ...props.emptyTemplate });

function openCreate() {
    editingTemplate.value = null;
    templateForm.reset();
    templateForm.defaults({ ...props.emptyTemplate });
    templateForm.clearErrors();
    modalOpen.value = true;
}

function openEdit(template: DutyShiftTemplate) {
    editingTemplate.value = template;
    templateForm.name = template.name;
    templateForm.duty_start_time = template.duty_start_time;
    templateForm.duty_end_time = template.duty_end_time;
    templateForm.grace_minutes = template.grace_minutes;
    templateForm.notes = template.notes ?? '';
    templateForm.is_active = template.is_active;
    templateForm.sort_order = template.sort_order;
    templateForm.clearErrors();
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editingTemplate.value = null;
    templateForm.reset();
    templateForm.defaults({ ...props.emptyTemplate });
}

function submitTemplate() {
    if (editingTemplate.value?.id) {
        templateForm.put(`/duty-shift-templates/${editingTemplate.value.id}`, {
            preserveScroll: true,
            onSuccess: closeModal,
        });

        return;
    }

    templateForm.post('/duty-shift-templates', {
        preserveScroll: true,
        onSuccess: closeModal,
    });
}

function destroyTemplate(template: DutyShiftTemplate) {
    if (confirm(`Delete fixed duty "${template.name}"?`)) {
        router.delete(`/duty-shift-templates/${template.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <UiCard class="mb-6">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Fixed duty shifts</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Reusable shift patterns you can assign to multiple employees at once.
                </p>
            </div>
            <UiButton v-if="can('duty-rosters.create')" size="sm" @click="openCreate">Add fixed duty</UiButton>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-100 text-left text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2.5 font-medium">Name</th>
                        <th class="px-3 py-2.5 font-medium">Start</th>
                        <th class="px-3 py-2.5 font-medium">End</th>
                        <th class="px-3 py-2.5 font-medium">Grace</th>
                        <th class="px-3 py-2.5 font-medium">Status</th>
                        <th v-if="can('duty-rosters.update') || can('duty-rosters.delete')" class="px-3 py-2.5 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="template in templates" :key="template.id!">
                        <td class="px-3 py-3 font-medium text-slate-900 dark:text-white">{{ template.name }}</td>
                        <td class="px-3 py-3 text-slate-600 dark:text-slate-400">{{ template.duty_start_time }}</td>
                        <td class="px-3 py-3 text-slate-600 dark:text-slate-400">{{ template.duty_end_time }}</td>
                        <td class="px-3 py-3 text-slate-600 dark:text-slate-400">{{ template.grace_minutes }}m</td>
                        <td class="px-3 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="template.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                            >
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td v-if="can('duty-rosters.update') || can('duty-rosters.delete')" class="px-3 py-3">
                            <div class="flex gap-2">
                                <UiButton v-if="can('duty-rosters.update')" size="sm" variant="ghost" @click="openEdit(template)">Edit</UiButton>
                                <UiButton
                                    v-if="can('duty-rosters.delete')"
                                    size="sm"
                                    variant="danger"
                                    @click="destroyTemplate(template)"
                                >
                                    Delete
                                </UiButton>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="templates.length === 0">
                        <td colspan="6" class="px-3 py-8 text-center text-slate-500 dark:text-slate-400">
                            No fixed duty shifts yet. Create one to speed up bulk assignments.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <UiModal
            :open="modalOpen"
            :title="editingTemplate ? 'Edit fixed duty shift' : 'Add fixed duty shift'"
            description="Define a reusable shift pattern with start time, end time, and grace minutes."
            @close="closeModal"
        >
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitTemplate">
                <div class="md:col-span-2">
                    <UiInput v-model="templateForm.name" label="Name" required :error="templateForm.errors.name" />
                </div>
                <UiInput v-model="templateForm.duty_start_time" label="Duty start" type="time" required :error="templateForm.errors.duty_start_time" />
                <UiInput v-model="templateForm.duty_end_time" label="Duty end" type="time" required :error="templateForm.errors.duty_end_time" />
                <UiInput v-model.number="templateForm.grace_minutes" label="Grace minutes" type="number" min="0" max="180" :error="templateForm.errors.grace_minutes" />
                <UiInput v-model.number="templateForm.sort_order" label="Sort order" type="number" min="0" :error="templateForm.errors.sort_order" />
                <div class="md:col-span-2">
                    <UiInput v-model="templateForm.notes" label="Notes" :error="templateForm.errors.notes" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 md:col-span-2 dark:text-slate-300">
                    <input v-model="templateForm.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                    Active
                </label>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <UiButton type="button" variant="ghost" @click="closeModal">Cancel</UiButton>
                    <UiButton type="submit" :disabled="templateForm.processing">
                        {{ editingTemplate ? 'Update fixed duty' : 'Create fixed duty' }}
                    </UiButton>
                </div>
            </form>
        </UiModal>
    </UiCard>
</template>
