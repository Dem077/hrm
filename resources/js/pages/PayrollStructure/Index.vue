<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Designation, DesignationPayrollItem, PayrollComponent } from '@/types/payroll';

const props = defineProps<{
    components: PayrollComponent[];
    designations: Designation[];
    emptyComponent: PayrollComponent;
    emptyDesignation: Designation;
    defaultDesignationItems: DesignationPayrollItem[];
}>();

const { can } = usePermissions();

const editingComponentId = ref<number | null>(null);
const designationModalOpen = ref(false);
const editingDesignationId = ref<number | null>(null);

const componentForm = useForm({ ...props.emptyComponent });
const designationForm = useForm({
    ...props.emptyDesignation,
    items: [] as DesignationPayrollItem[],
});

const optionalComponents = computed(() => props.components.filter((component) => !component.is_mandatory && component.is_active));

const designationTotals = computed(() => {
    const additions = designationForm.items
        .filter((item) => item.type === 'addition')
        .reduce((sum, item) => sum + Number(item.amount || 0), 0);

    const deductions = designationForm.items
        .filter((item) => item.type === 'deduction')
        .reduce((sum, item) => sum + Number(item.amount || 0), 0);

    return {
        additions,
        deductions,
        net: additions - deductions,
    };
});

const availableOptionalComponents = computed(() => {
    const assignedIds = new Set(designationForm.items.map((item) => item.payroll_component_id));

    return optionalComponents.value.filter((component) => !assignedIds.has(component.id!));
});

function formatMoney(amount: number) {
    return new Intl.NumberFormat('en-PK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

function editComponent(component: PayrollComponent) {
    editingComponentId.value = component.id;
    componentForm.name = component.name;
    componentForm.code = component.code ?? '';
    componentForm.type = component.type;
    componentForm.is_mandatory = component.is_mandatory;
    componentForm.sort_order = component.sort_order;
    componentForm.is_active = component.is_active;
}

function cancelComponentEdit() {
    editingComponentId.value = null;
    componentForm.reset();
    componentForm.defaults({ ...props.emptyComponent });
}

function submitComponent() {
    if (editingComponentId.value) {
        componentForm.put(`/payroll-structure/components/${editingComponentId.value}`, {
            preserveScroll: true,
            onSuccess: cancelComponentEdit,
        });

        return;
    }

    componentForm.post('/payroll-structure/components', {
        preserveScroll: true,
        onSuccess: () => {
            componentForm.reset();
            componentForm.defaults({ ...props.emptyComponent });
        },
    });
}

function destroyComponent(id: number, name: string) {
    if (confirm(`Delete payroll component "${name}"?`)) {
        router.delete(`/payroll-structure/components/${id}`, { preserveScroll: true });
    }
}

function openCreateDesignation() {
    editingDesignationId.value = null;
    designationForm.reset();
    designationForm.defaults({
        ...props.emptyDesignation,
        items: props.defaultDesignationItems.map((item) => ({ ...item })),
    });
    designationForm.items = props.defaultDesignationItems.map((item) => ({ ...item }));
    designationForm.clearErrors();
    designationModalOpen.value = true;
}

function openEditDesignation(designation: Designation) {
    editingDesignationId.value = designation.id;
    designationForm.name = designation.name;
    designationForm.code = designation.code ?? '';
    designationForm.description = designation.description ?? '';
    designationForm.sort_order = designation.sort_order;
    designationForm.is_active = designation.is_active;
    designationForm.items = designation.items.map((item) => ({ ...item }));
    designationForm.clearErrors();
    designationModalOpen.value = true;
}

function closeDesignationModal() {
    designationModalOpen.value = false;
    editingDesignationId.value = null;
    designationForm.reset();
    designationForm.defaults({
        ...props.emptyDesignation,
        items: [],
    });
}

function addOptionalComponent(componentId: number) {
    const component = props.components.find((item) => item.id === componentId);

    if (!component) {
        return;
    }

    designationForm.items.push({
        payroll_component_id: component.id!,
        name: component.name,
        type: component.type,
        type_label: component.type_label,
        is_mandatory: component.is_mandatory,
        amount: 0,
    });
}

function removeOptionalItem(item: DesignationPayrollItem) {
    if (item.is_mandatory) {
        return;
    }

    designationForm.items = designationForm.items.filter(
        (row) => row.payroll_component_id !== item.payroll_component_id,
    );
}

function submitDesignation() {
    if (editingDesignationId.value) {
        designationForm.put(`/payroll-structure/designations/${editingDesignationId.value}`, {
            preserveScroll: true,
            onSuccess: closeDesignationModal,
        });

        return;
    }

    designationForm.post('/payroll-structure/designations', {
        preserveScroll: true,
        onSuccess: closeDesignationModal,
    });
}

function destroyDesignation(id: number, name: string) {
    if (confirm(`Delete designation "${name}"?`)) {
        router.delete(`/payroll-structure/designations/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Payroll Structure" />

    <AppLayout>
        <PageHeader
            title="Payroll structure"
            description="Define payroll additions and deductions, then assign amounts per designation. Basic Salary is mandatory for every designation."
        />

        <div class="space-y-8">
            <UiCard padding="none">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Payroll components</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Additions and deductions used across designations. Mandatory components apply to every designation.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Name</th>
                                <th class="px-5 py-3.5 font-medium">Type</th>
                                <th class="px-5 py-3.5 font-medium">Mandatory</th>
                                <th class="px-5 py-3.5 font-medium">Status</th>
                                <th class="px-5 py-3.5 font-medium">Designations</th>
                                <th v-if="can('payroll-structure.update')" class="px-5 py-3.5 font-medium">Actions</th>
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
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ component.designations_count ?? 0 }}</td>
                                <td v-if="can('payroll-structure.update')" class="px-5 py-4">
                                    <div v-if="!component.is_system_mandatory" class="flex gap-2">
                                        <UiButton size="sm" variant="ghost" @click="editComponent(component)">Edit</UiButton>
                                        <UiButton
                                            size="sm"
                                            variant="danger"
                                            @click="destroyComponent(component.id!, component.name)"
                                        >
                                            Delete
                                        </UiButton>
                                    </div>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="can('payroll-structure.update')" class="border-t border-slate-100 px-5 py-5 dark:border-slate-800">
                    <h3 class="mb-4 text-sm font-semibold text-slate-900 dark:text-white">
                        {{ editingComponentId ? 'Edit component' : 'Add component' }}
                    </h3>

                    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitComponent">
                        <UiInput v-model="componentForm.name" label="Name" required :error="componentForm.errors.name" />
                        <UiInput v-model="componentForm.code" label="Code" :error="componentForm.errors.code" />
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Type</label>
                            <select
                                v-model="componentForm.type"
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                            >
                                <option value="addition">Addition</option>
                                <option value="deduction">Deduction</option>
                            </select>
                            <p v-if="componentForm.errors.type" class="mt-1 text-sm text-red-600">{{ componentForm.errors.type }}</p>
                        </div>
                        <UiInput
                            v-model.number="componentForm.sort_order"
                            label="Sort order"
                            type="number"
                            min="0"
                            :error="componentForm.errors.sort_order"
                        />
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="componentForm.is_mandatory" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                            Mandatory for all designations
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input v-model="componentForm.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                            Active
                        </label>
                        <div class="flex gap-2 md:col-span-2">
                            <UiButton type="submit" :disabled="componentForm.processing">
                                {{ editingComponentId ? 'Update component' : 'Add component' }}
                            </UiButton>
                            <UiButton v-if="editingComponentId" type="button" variant="ghost" @click="cancelComponentEdit">Cancel</UiButton>
                        </div>
                    </form>
                </div>
            </UiCard>

            <UiCard padding="none">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Designations</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Each designation has its own amounts for additions and deductions.
                        </p>
                    </div>
                    <UiButton v-if="can('payroll-structure.update')" @click="openCreateDesignation">Add designation</UiButton>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Name</th>
                                <th class="px-5 py-3.5 font-medium">Components</th>
                                <th class="px-5 py-3.5 font-medium">Additions</th>
                                <th class="px-5 py-3.5 font-medium">Deductions</th>
                                <th class="px-5 py-3.5 font-medium">Net</th>
                                <th class="px-5 py-3.5 font-medium">Status</th>
                                <th v-if="can('payroll-structure.update')" class="px-5 py-3.5 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="designation in designations" :key="designation.id!">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ designation.name }}</div>
                                    <div v-if="designation.code" class="text-xs text-slate-500 dark:text-slate-400">{{ designation.code }}</div>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ designation.items.length }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatMoney(designation.totals?.additions ?? 0) }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatMoney(designation.totals?.deductions ?? 0) }}</td>
                                <td class="px-5 py-4 font-medium text-slate-900 dark:text-white">{{ formatMoney(designation.totals?.net ?? 0) }}</td>
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
                                <td v-if="can('payroll-structure.update')" class="px-5 py-4">
                                    <div class="flex gap-2">
                                        <UiButton size="sm" variant="ghost" @click="openEditDesignation(designation)">Edit</UiButton>
                                        <UiButton size="sm" variant="danger" @click="destroyDesignation(designation.id!, designation.name)">Delete</UiButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="designations.length === 0">
                                <td colspan="7" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">
                                    No designations yet. Create one to set payroll amounts.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>
        </div>

        <UiModal
            :open="designationModalOpen"
            :title="editingDesignationId ? 'Edit designation' : 'Add designation'"
            description="Set amounts for each payroll component assigned to this designation."
            max-width="xl"
            @close="closeDesignationModal"
        >
            <form class="space-y-5" @submit.prevent="submitDesignation">
                <div class="grid gap-4 md:grid-cols-2">
                    <UiInput v-model="designationForm.name" label="Name" required :error="designationForm.errors.name" />
                    <UiInput v-model="designationForm.code" label="Code" :error="designationForm.errors.code" />
                    <UiInput
                        v-model.number="designationForm.sort_order"
                        label="Sort order"
                        type="number"
                        min="0"
                        :error="designationForm.errors.sort_order"
                    />
                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700 dark:text-slate-300">
                        <input v-model="designationForm.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                        Active
                    </label>
                    <div class="md:col-span-2">
                        <UiInput
                            v-model="designationForm.description"
                            label="Description"
                            :error="designationForm.errors.description"
                        />
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Payroll amounts</h3>
                        <div v-if="availableOptionalComponents.length > 0" class="flex items-center gap-2">
                            <select
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                                @change="addOptionalComponent(Number(($event.target as HTMLSelectElement).value)); ($event.target as HTMLSelectElement).value = ''"
                            >
                                <option value="">Add optional component…</option>
                                <option v-for="component in availableOptionalComponents" :key="component.id!" :value="component.id!">
                                    {{ component.name }} ({{ component.type_label ?? component.type }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <p v-if="designationForm.errors.items" class="mb-3 text-sm text-red-600">{{ designationForm.errors.items }}</p>

                    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-slate-500 dark:bg-surface-elevated dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Component</th>
                                    <th class="px-4 py-3 font-medium">Type</th>
                                    <th class="px-4 py-3 font-medium">Amount</th>
                                    <th class="px-4 py-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="(item, index) in designationForm.items" :key="item.payroll_component_id">
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                        {{ item.name }}
                                        <span v-if="item.is_mandatory" class="ml-1 text-xs text-slate-400">(mandatory)</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ item.type_label ?? item.type }}</td>
                                    <td class="px-4 py-3">
                                        <UiInput
                                            v-model.number="item.amount"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :error="designationForm.errors[`items.${index}.amount`]"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <UiButton
                                            v-if="!item.is_mandatory"
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            @click="removeOptionalItem(item)"
                                        >
                                            Remove
                                        </UiButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 grid gap-2 rounded-lg bg-slate-50 p-4 text-sm dark:bg-surface-elevated md:grid-cols-3">
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">Additions:</span>
                            <span class="ml-2 font-medium text-slate-900 dark:text-white">{{ formatMoney(designationTotals.additions) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">Deductions:</span>
                            <span class="ml-2 font-medium text-slate-900 dark:text-white">{{ formatMoney(designationTotals.deductions) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">Net:</span>
                            <span class="ml-2 font-semibold text-slate-900 dark:text-white">{{ formatMoney(designationTotals.net) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <UiButton type="button" variant="ghost" @click="closeDesignationModal">Cancel</UiButton>
                    <UiButton type="submit" :disabled="designationForm.processing">
                        {{ editingDesignationId ? 'Update designation' : 'Create designation' }}
                    </UiButton>
                </div>
            </form>
        </UiModal>
    </AppLayout>
</template>
