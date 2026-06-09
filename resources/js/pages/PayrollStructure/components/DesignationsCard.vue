<script setup lang="ts">
import { ref } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import { formatPayrollMoney } from '@/lib/payroll';
import DesignationFormModal from '@/pages/PayrollStructure/components/DesignationFormModal.vue';
import type { Designation, DesignationPayrollItem, PayrollComponent } from '@/types/payroll';

defineProps<{
    designations: Designation[];
    components: PayrollComponent[];
    emptyDesignation: Designation;
    defaultDesignationItems: DesignationPayrollItem[];
    canManage: boolean;
}>();

const emit = defineEmits<{
    delete: [id: number, name: string];
}>();

const modalOpen = ref(false);
const editingDesignation = ref<Designation | null>(null);

function openCreate() {
    editingDesignation.value = null;
    modalOpen.value = true;
}

function openEdit(designation: Designation) {
    editingDesignation.value = designation;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editingDesignation.value = null;
}
</script>

<template>
    <UiCard padding="none">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Designations</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Salary packages per role. Fixed net excludes daily lines until attendance is applied.
                </p>
            </div>
            <UiButton v-if="canManage" @click="openCreate">Add designation</UiButton>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5 font-medium">Name</th>
                        <th class="px-5 py-3.5 font-medium">Components</th>
                        <th class="px-5 py-3.5 font-medium">Fixed additions</th>
                        <th class="px-5 py-3.5 font-medium">Fixed deductions</th>
                        <th class="px-5 py-3.5 font-medium">Fixed net</th>
                        <th class="px-5 py-3.5 font-medium">Status</th>
                        <th v-if="canManage" class="px-5 py-3.5 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="designation in designations" :key="designation.id!">
                        <td class="px-5 py-4">
                            <div class="font-medium text-slate-900 dark:text-white">{{ designation.name }}</div>
                            <div v-if="designation.code" class="text-xs text-slate-500 dark:text-slate-400">{{ designation.code }}</div>
                        </td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ designation.items.length }}</td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatPayrollMoney(designation.totals?.additions ?? 0) }}</td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatPayrollMoney(designation.totals?.deductions ?? 0) }}</td>
                        <td class="px-5 py-4 font-medium text-slate-900 dark:text-white">
                            {{ formatPayrollMoney(designation.totals?.net ?? 0) }}
                            <span
                                v-if="designation.totals?.has_daily"
                                class="ml-1 text-xs font-normal text-slate-400"
                                :title="`${designation.totals.daily_count} daily component(s) excluded`"
                            >
                                + daily
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    designation.is_active
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                "
                            >
                                {{ designation.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td v-if="canManage" class="px-5 py-4">
                            <div class="flex gap-2">
                                <UiButton size="sm" variant="ghost" @click="openEdit(designation)">Edit</UiButton>
                                <UiButton size="sm" variant="danger" @click="emit('delete', designation.id!, designation.name)">Delete</UiButton>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="designations.length === 0">
                        <td colspan="7" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">
                            No designations yet. Create one to define payroll amounts per role.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <DesignationFormModal
            :open="modalOpen"
            :designation="editingDesignation"
            :empty-designation="emptyDesignation"
            :default-items="defaultDesignationItems"
            :components="components"
            @close="closeModal"
            @saved="closeModal"
        />
    </UiCard>
</template>
