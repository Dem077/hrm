<script setup lang="ts">
import { computed, ref } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import type { SelectOption } from '@/types/hrm';

export type PickerEmployee = {
    id: number;
    name: string;
    staff_id: string;
    department_id?: number | null;
};

const props = withDefaults(
    defineProps<{
        modelValue: number[];
        employees: PickerEmployee[];
        departments?: SelectOption[];
        error?: string;
        hint?: string;
        label?: string;
    }>(),
    {
        departments: () => [],
        hint: 'Only employees with a login account are listed.',
        label: 'Allowed employees',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const searchText = ref('');
const departmentFilter = ref<string | number>('');

const filteredEmployees = computed(() => {
    const needle = searchText.value.trim().toLowerCase();
    const departmentId = departmentFilter.value === '' || departmentFilter.value === null
        ? null
        : Number(departmentFilter.value);

    return props.employees.filter((employee) => {
        if (departmentId !== null && !Number.isNaN(departmentId) && employee.department_id !== departmentId) {
            return false;
        }

        if (needle === '') {
            return true;
        }

        const haystack = [employee.name, employee.staff_id].join(' ').toLowerCase();

        return haystack.includes(needle);
    });
});

const filteredIds = computed(() => filteredEmployees.value.map((employee) => employee.id));

const allFilteredSelected = computed(() => {
    if (filteredIds.value.length === 0) {
        return false;
    }

    return filteredIds.value.every((id) => props.modelValue.includes(id));
});

const selectedOutsideFiltersCount = computed(() => {
    const visible = new Set(filteredIds.value);

    return props.modelValue.filter((id) => !visible.has(id)).length;
});

function toggleEmployee(employeeId: number): void {
    if (props.modelValue.includes(employeeId)) {
        emit(
            'update:modelValue',
            props.modelValue.filter((id) => id !== employeeId),
        );
        return;
    }

    emit('update:modelValue', [...props.modelValue, employeeId]);
}

function toggleSelectAllFiltered(): void {
    if (allFilteredSelected.value) {
        const filtered = new Set(filteredIds.value);
        emit(
            'update:modelValue',
            props.modelValue.filter((id) => !filtered.has(id)),
        );
        return;
    }

    const next = new Set(props.modelValue);
    filteredIds.value.forEach((id) => next.add(id));
    emit('update:modelValue', Array.from(next));
}

function clearSelection(): void {
    emit('update:modelValue', []);
}
</script>

<template>
    <div>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ label }}</p>
            <p class="text-xs text-slate-500">
                {{ modelValue.length }} selected
                <template v-if="selectedOutsideFiltersCount">
                    · {{ selectedOutsideFiltersCount }} outside filters
                </template>
            </p>
        </div>
        <p v-if="hint" class="mb-3 text-xs text-slate-500">{{ hint }}</p>

        <div class="mb-3 grid gap-3 sm:grid-cols-2">
            <UiInput
                v-model="searchText"
                label="Search"
                placeholder="Name or staff ID"
            />
            <UiSelect
                v-if="departments.length > 0"
                :model-value="departmentFilter"
                label="Department"
                @update:model-value="departmentFilter = $event ?? ''"
            >
                <option value="">All departments</option>
                <option v-for="department in departments" :key="department.id" :value="department.id">
                    {{ department.name ?? department.label }}
                </option>
            </UiSelect>
        </div>

        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input
                    type="checkbox"
                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    :checked="allFilteredSelected"
                    :disabled="filteredIds.length === 0"
                    @change="toggleSelectAllFiltered"
                />
                <span>Select filtered ({{ filteredIds.length }})</span>
            </label>
            <UiButton
                v-if="modelValue.length"
                type="button"
                size="sm"
                variant="ghost"
                @click="clearSelection"
            >
                Clear selection
            </UiButton>
        </div>

        <div class="max-h-56 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <label
                v-for="employee in filteredEmployees"
                :key="employee.id"
                class="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2 text-sm hover:bg-slate-50 dark:hover:bg-surface-muted"
            >
                <input
                    type="checkbox"
                    class="mt-0.5 rounded border-slate-300 text-brand-600"
                    :checked="modelValue.includes(employee.id)"
                    @change="toggleEmployee(employee.id)"
                />
                <span>
                    <span class="font-medium">{{ employee.name }}</span>
                    <span class="block text-xs text-slate-500">{{ employee.staff_id }}</span>
                </span>
            </label>
            <p v-if="employees.length === 0" class="text-sm text-slate-500">
                No employees with login accounts found.
            </p>
            <p v-else-if="filteredEmployees.length === 0" class="text-sm text-slate-500">
                No employees match this search or filter.
            </p>
        </div>
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
