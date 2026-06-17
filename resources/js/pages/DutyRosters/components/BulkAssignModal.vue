<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import DutyDateSelector, { type DutyDateMode } from '@/pages/DutyRosters/components/DutyDateSelector.vue';
import type { DutyShiftTemplate, SelectOption, ShiftEmployeeOption } from '@/types/hrm';

const props = defineProps<{
    open: boolean;
    canViewAll: boolean;
    shiftEmployees: ShiftEmployeeOption[];
    departments: SelectOption[];
    dutyShiftTemplates: DutyShiftTemplate[];
    emptyBulkAssign: {
        employee_ids: number[];
        date_mode: DutyDateMode;
        from_date: string;
        to_date: string;
        duty_dates: string[];
        duty_shift_template_id: number | null;
        duty_start_time: string;
        duty_end_time: string;
        grace_minutes: number;
        notes: string;
        skip_weekends: boolean;
    };
}>();

const emit = defineEmits<{
    close: [];
}>();

const bulkForm = useForm({ ...props.emptyBulkAssign });
const departmentFilter = ref<number | ''>('');

const activeTemplates = computed(() => props.dutyShiftTemplates.filter((template) => template.is_active));

const filteredEmployees = computed(() => {
    if (!departmentFilter.value) {
        return props.shiftEmployees;
    }

    return props.shiftEmployees.filter((employee) => employee.department_id === departmentFilter.value);
});

const allFilteredSelected = computed(() => {
    if (filteredEmployees.value.length === 0) {
        return false;
    }

    return filteredEmployees.value.every((employee) => bulkForm.employee_ids.includes(employee.id));
});

const useTemplate = computed(() => Boolean(bulkForm.duty_shift_template_id));

const canSubmit = computed(() => {
    if (bulkForm.employee_ids.length === 0) {
        return false;
    }

    if (bulkForm.date_mode === 'specific') {
        return bulkForm.duty_dates.length > 0;
    }

    return Boolean(bulkForm.from_date && bulkForm.to_date);
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        bulkForm.reset();
        bulkForm.defaults({ ...props.emptyBulkAssign });
        bulkForm.clearErrors();
        departmentFilter.value = '';
    },
);

watch(
    () => bulkForm.duty_shift_template_id,
    (templateId) => {
        if (!templateId) {
            return;
        }

        const template = props.dutyShiftTemplates.find((item) => item.id === templateId);

        if (!template) {
            return;
        }

        bulkForm.duty_start_time = template.duty_start_time;
        bulkForm.duty_end_time = template.duty_end_time;
        bulkForm.grace_minutes = template.grace_minutes;
    },
);

function toggleEmployee(employeeId: number) {
    if (bulkForm.employee_ids.includes(employeeId)) {
        bulkForm.employee_ids = bulkForm.employee_ids.filter((id) => id !== employeeId);

        return;
    }

    bulkForm.employee_ids = [...bulkForm.employee_ids, employeeId];
}

function toggleSelectAll() {
    if (allFilteredSelected.value) {
        const filteredIds = new Set(filteredEmployees.value.map((employee) => employee.id));
        bulkForm.employee_ids = bulkForm.employee_ids.filter((id) => !filteredIds.has(id));

        return;
    }

    const merged = new Set([...bulkForm.employee_ids, ...filteredEmployees.value.map((employee) => employee.id)]);
    bulkForm.employee_ids = [...merged];
}

function submitBulkAssign() {
    bulkForm.post('/duty-rosters/bulk-assign', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <UiModal
        :open="open"
        title="Bulk assign duty"
        description="Assign the same duty shift to multiple employees using a date range or specific dates. Existing entries will be updated."
        @close="emit('close')"
    >
        <form class="grid gap-4" @submit.prevent="submitBulkAssign">
            <DutyDateSelector
                :date-mode="bulkForm.date_mode"
                :from-date="bulkForm.from_date"
                :to-date="bulkForm.to_date"
                :duty-dates="bulkForm.duty_dates"
                :skip-weekends="bulkForm.skip_weekends"
                :errors="bulkForm.errors"
                @update:date-mode="bulkForm.date_mode = $event"
                @update:from-date="bulkForm.from_date = $event"
                @update:to-date="bulkForm.to_date = $event"
                @update:duty-dates="bulkForm.duty_dates = $event"
                @update:skip-weekends="bulkForm.skip_weekends = $event"
            />

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Fixed duty shift</label>
                <select
                    v-model="bulkForm.duty_shift_template_id"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                >
                    <option :value="null">Custom times</option>
                    <option v-for="template in activeTemplates" :key="template.id!" :value="template.id">
                        {{ template.name }} ({{ template.duty_start_time }} – {{ template.duty_end_time }})
                    </option>
                </select>
                <p v-if="bulkForm.errors.duty_shift_template_id" class="mt-1 text-sm text-red-600">
                    {{ bulkForm.errors.duty_shift_template_id }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <UiInput
                    v-model="bulkForm.duty_start_time"
                    label="Duty start"
                    type="time"
                    required
                    :disabled="useTemplate"
                    :error="bulkForm.errors.duty_start_time"
                />
                <UiInput
                    v-model="bulkForm.duty_end_time"
                    label="Duty end"
                    type="time"
                    required
                    :disabled="useTemplate"
                    :error="bulkForm.errors.duty_end_time"
                />
                <UiInput
                    v-model.number="bulkForm.grace_minutes"
                    label="Grace minutes"
                    type="number"
                    min="0"
                    max="180"
                    :disabled="useTemplate"
                    :error="bulkForm.errors.grace_minutes"
                />
            </div>

            <UiInput v-model="bulkForm.notes" label="Notes" :error="bulkForm.errors.notes" />

            <div>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Employees</label>
                    <UiButton type="button" size="sm" variant="ghost" @click="toggleSelectAll">
                        {{ allFilteredSelected ? 'Clear filtered' : 'Select all filtered' }}
                    </UiButton>
                </div>

                <div v-if="canViewAll" class="mb-3">
                    <select
                        v-model="departmentFilter"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                    >
                        <option value="">All departments</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </select>
                </div>

                <div class="max-h-48 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                    <label
                        v-for="employee in filteredEmployees"
                        :key="employee.id"
                        class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300"
                    >
                        <input
                            type="checkbox"
                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            :checked="bulkForm.employee_ids.includes(employee.id)"
                            @change="toggleEmployee(employee.id)"
                        />
                        {{ employee.label }}
                    </label>
                    <p v-if="filteredEmployees.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
                        No shift duty employees match this filter.
                    </p>
                </div>
                <p v-if="bulkForm.errors.employee_ids" class="mt-1 text-sm text-red-600">{{ bulkForm.errors.employee_ids }}</p>
            </div>

            <div class="flex justify-end gap-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" :disabled="bulkForm.processing || !canSubmit">
                    Assign duty
                </UiButton>
            </div>
        </form>
    </UiModal>
</template>
