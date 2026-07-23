<script setup lang="ts">
import { ref } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import { formatPayrollMoney } from '@/lib/payroll';
import ComponentFormModal from '@/pages/PayrollStructure/components/ComponentFormModal.vue';
import GlobalRateModal from '@/pages/PayrollStructure/components/GlobalRateModal.vue';
import type { PayrollComponent } from '@/types/payroll';

defineProps<{
    components: PayrollComponent[];
    emptyComponent: PayrollComponent;
    canManage: boolean;
}>();

const emit = defineEmits<{
    delete: [id: number, name: string];
}>();

const modalOpen = ref(false);
const editingComponent = ref<PayrollComponent | null>(null);
const rateModalOpen = ref(false);
const rateComponent = ref<PayrollComponent | null>(null);

function openCreate() {
    editingComponent.value = null;
    modalOpen.value = true;
}

function openEdit(component: PayrollComponent) {
    editingComponent.value = component;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editingComponent.value = null;
}

function openRate(component: PayrollComponent) {
    rateComponent.value = component;
    rateModalOpen.value = true;
}

function closeRateModal() {
    rateModalOpen.value = false;
    rateComponent.value = null;
}
</script>

<template>
    <UiCard padding="none">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Payroll components</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Building blocks for grade packages. Late Fine and Absent Fee use one company-wide rate.
                </p>
            </div>
            <UiButton v-if="canManage" @click="openCreate">Add component</UiButton>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5 font-medium">Name</th>
                        <th class="px-5 py-3.5 font-medium">Type</th>
                        <th class="px-5 py-3.5 font-medium">Calculation</th>
                        <th class="px-5 py-3.5 font-medium">Company rate</th>
                        <th class="px-5 py-3.5 font-medium">Mandatory</th>
                        <th class="px-5 py-3.5 font-medium">Status</th>
                        <th class="px-5 py-3.5 font-medium">Grades</th>
                        <th v-if="canManage" class="px-5 py-3.5 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="component in components" :key="component.id!">
                        <td class="px-5 py-4">
                            <div class="font-medium text-slate-900 dark:text-white">{{ component.name }}</div>
                            <div v-if="component.code" class="text-xs text-slate-500 dark:text-slate-400">{{ component.code }}</div>
                        </td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ component.type_label ?? component.type }}</td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                            {{ component.calculation_method_label ?? component.calculation_method }}
                        </td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                            <template v-if="component.is_custom_formula || component.calculation_method === 'custom_formula'">
                                <span class="font-mono text-xs">{{ component.calculation_formula || '—' }}</span>
                                <span class="block text-xs text-slate-400">Custom formula</span>
                            </template>
                            <template v-else-if="component.uses_global_rate">
                                <template v-if="component.is_percentage_rate">
                                    {{ Number(component.global_rate ?? 0) }}%
                                    <span class="block text-xs text-slate-400">{{ component.amount_label }}</span>
                                </template>
                                <template v-else>
                                    {{ formatPayrollMoney(Number(component.global_rate ?? 0)) }}
                                    <span class="block text-xs text-slate-400">{{ component.amount_label }}</span>
                                </template>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                            {{ component.is_mandatory ? 'Yes' : 'No' }}
                            <span v-if="component.is_system_mandatory" class="ml-1 text-xs text-slate-400">(system)</span>
                        </td>
                        <td class="px-5 py-4">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    component.is_active
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                "
                            >
                                {{ component.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ component.grades_count ?? 0 }}</td>
                        <td v-if="canManage" class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <UiButton
                                    v-if="(component.allowed_calculation_methods?.length ?? 0) > 0"
                                    size="sm"
                                    variant="ghost"
                                    @click="openRate(component)"
                                >
                                    Configure
                                </UiButton>
                                <template v-if="!component.is_system_mandatory">
                                    <UiButton size="sm" variant="ghost" @click="openEdit(component)">Edit</UiButton>
                                    <UiButton size="sm" variant="danger" @click="emit('delete', component.id!, component.name)">Delete</UiButton>
                                </template>
                                <span
                                    v-else-if="(component.allowed_calculation_methods?.length ?? 0) === 0"
                                    class="text-slate-400"
                                >
                                    —
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ComponentFormModal
            :open="modalOpen"
            :component="editingComponent"
            :empty-component="emptyComponent"
            @close="closeModal"
            @saved="closeModal"
        />

        <GlobalRateModal
            :open="rateModalOpen"
            :component="rateComponent"
            @close="closeRateModal"
            @saved="closeRateModal"
        />
    </UiCard>
</template>
