<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import BulkAssignModal from '@/pages/DutyRosters/components/BulkAssignModal.vue';
import DutyDateSelector, { type DutyDateMode } from '@/pages/DutyRosters/components/DutyDateSelector.vue';
import FixedDutyShiftsCard from '@/pages/DutyRosters/components/FixedDutyShiftsCard.vue';
import type { DutyRosterEntry, DutyShiftTemplate, SelectOption, ShiftEmployeeOption } from '@/types/hrm';

const props = defineProps<{
    entries: DutyRosterEntry[];
    canViewAll: boolean;
    scopedDepartmentId: number | null;
    filters: {
        from: string;
        to: string;
        department_id: number | null;
        employee_id: number | null;
    };
    departments: SelectOption[];
    shiftEmployees: ShiftEmployeeOption[];
    allShiftEmployees: ShiftEmployeeOption[];
    dutyShiftTemplates: DutyShiftTemplate[];
    emptyEntry: DutyRosterEntry;
    emptyTemplate: DutyShiftTemplate;
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

type EntryForm = DutyRosterEntry & {
    date_mode: DutyDateMode;
    from_date: string;
    to_date: string;
    duty_dates: string[];
    skip_weekends: boolean;
};

const { can } = usePermissions();

const filterForm = reactive({
    from: props.filters.from,
    to: props.filters.to,
    department_id: props.filters.department_id ?? '',
    employee_id: props.filters.employee_id ?? '',
});

const modalOpen = ref(false);
const bulkModalOpen = ref(false);
const editingEntry = ref<DutyRosterEntry | null>(null);
function createEntryDefaults(): EntryForm {
    return {
        ...props.emptyEntry,
        date_mode: 'single',
        from_date: props.emptyBulkAssign.from_date,
        to_date: props.emptyBulkAssign.to_date,
        duty_dates: [],
        skip_weekends: false,
    };
}

const entryForm = useForm<EntryForm>(createEntryDefaults());

function applyFilters() {
    router.get('/duty-rosters', filterForm, {
        preserveState: true,
        replace: true,
    });
}

function openCreate() {
    editingEntry.value = null;
    entryForm.reset();
    entryForm.defaults(createEntryDefaults());
    entryForm.clearErrors();
    modalOpen.value = true;
}

function openEdit(entry: DutyRosterEntry) {
    editingEntry.value = entry;
    entryForm.employee_id = entry.employee_id;
    entryForm.duty_date = entry.duty_date;
    entryForm.duty_start_time = entry.duty_start_time;
    entryForm.duty_end_time = entry.duty_end_time;
    entryForm.grace_minutes = entry.grace_minutes;
    entryForm.notes = entry.notes ?? '';
    entryForm.clearErrors();
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editingEntry.value = null;
    entryForm.reset();
    entryForm.defaults(createEntryDefaults());
}

function submitEntry() {
    if (editingEntry.value?.id) {
        entryForm.put(`/duty-rosters/${editingEntry.value.id}`, {
            preserveScroll: true,
            onSuccess: closeModal,
        });

        return;
    }

    if (entryForm.date_mode === 'single') {
        entryForm.post('/duty-rosters', {
            preserveScroll: true,
            onSuccess: closeModal,
        });

        return;
    }

    entryForm
        .transform((data) => ({
            employee_ids: data.employee_id ? [data.employee_id] : [],
            date_mode: data.date_mode,
            from_date: data.from_date,
            to_date: data.to_date,
            duty_dates: data.duty_dates,
            duty_start_time: data.duty_start_time,
            duty_end_time: data.duty_end_time,
            grace_minutes: data.grace_minutes,
            notes: data.notes,
            skip_weekends: data.skip_weekends,
        }))
        .post('/duty-rosters/bulk-assign', {
            preserveScroll: true,
            onSuccess: closeModal,
        });
}

function destroyEntry(id: number, employeeName: string, date: string) {
    if (confirm(`Delete duty roster for ${employeeName} on ${formatDate(date)}?`)) {
        router.delete(`/duty-rosters/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Duty Roster" />

    <AppLayout>
        <PageHeader
            title="Duty roster"
            :description="
                canViewAll
                    ? 'Assign daily duty timings for shift duty employees. Roster entries override global and temporary duty policies on the attendance sheet.'
                    : 'Assign daily duty timings for shift staff in your department. Roster entries override global and temporary duty policies on the attendance sheet.'
            "
        >
            <template #actions>
                <UiButton v-if="can('duty-rosters.create')" variant="ghost" @click="bulkModalOpen = true">Bulk assign</UiButton>
                <UiButton v-if="can('duty-rosters.create')" @click="openCreate">Add duty</UiButton>
            </template>
        </PageHeader>

        <FixedDutyShiftsCard :templates="dutyShiftTemplates" :empty-template="emptyTemplate" />

        <UiCard class="mb-6">
            <form class="grid gap-4" :class="canViewAll ? 'md:grid-cols-5' : 'md:grid-cols-4'" @submit.prevent="applyFilters">
                <UiInput v-model="filterForm.from" label="From" type="date" />
                <UiInput v-model="filterForm.to" label="To" type="date" />
                <div v-if="canViewAll">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Department</label>
                    <select
                        v-model="filterForm.department_id"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                    >
                        <option value="">All departments</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Employee</label>
                    <select
                        v-model="filterForm.employee_id"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                    >
                        <option value="">{{ canViewAll ? 'All shift employees' : 'All department shift staff' }}</option>
                        <option v-for="employee in shiftEmployees" :key="employee.id" :value="employee.id">
                            {{ employee.label }}
                        </option>
                    </select>
                </div>
                <div class="flex items-end">
                    <UiButton type="submit" class="w-full">Apply</UiButton>
                </div>
            </form>
        </UiCard>

        <UiCard padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Date</th>
                            <th class="px-5 py-3.5 font-medium">Employee</th>
                            <th class="px-5 py-3.5 font-medium">Department</th>
                            <th class="px-5 py-3.5 font-medium">Duty start</th>
                            <th class="px-5 py-3.5 font-medium">Duty end</th>
                            <th class="px-5 py-3.5 font-medium">Grace</th>
                            <th class="px-5 py-3.5 font-medium">Notes</th>
                            <th v-if="can('duty-rosters.update') || can('duty-rosters.delete')" class="px-5 py-3.5 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="entry in entries" :key="entry.id!">
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ formatDate(entry.duty_date) }}</td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-slate-900 dark:text-white">{{ entry.employee?.name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ entry.employee?.staff_id }}</div>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ entry.employee?.department ?? '—' }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ entry.duty_start_time }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ entry.duty_end_time }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ entry.grace_minutes }}m</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ entry.notes || '—' }}</td>
                            <td v-if="can('duty-rosters.update') || can('duty-rosters.delete')" class="px-5 py-4">
                                <div class="flex gap-2">
                                    <UiButton v-if="can('duty-rosters.update')" size="sm" variant="ghost" @click="openEdit(entry)">Edit</UiButton>
                                    <UiButton
                                        v-if="can('duty-rosters.delete')"
                                        size="sm"
                                        variant="danger"
                                        @click="destroyEntry(entry.id!, entry.employee?.name ?? 'employee', entry.duty_date)"
                                    >
                                        Delete
                                    </UiButton>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="entries.length === 0">
                            <td colspan="8" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">
                                No duty roster entries for this period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <BulkAssignModal
            :open="bulkModalOpen"
            :can-view-all="canViewAll"
            :shift-employees="allShiftEmployees"
            :departments="departments"
            :duty-shift-templates="dutyShiftTemplates"
            :empty-bulk-assign="emptyBulkAssign"
            @close="bulkModalOpen = false"
        />

        <UiModal
            :open="modalOpen"
            :title="editingEntry ? 'Edit duty assignment' : 'Add duty assignment'"
            description="Only employees with shift duty can be assigned roster entries."
            @close="closeModal"
        >
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submitEntry">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Employee</label>
                    <select
                        v-model="entryForm.employee_id"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                        required
                    >
                        <option :value="null" disabled>Select shift employee</option>
                        <option v-for="employee in shiftEmployees" :key="employee.id" :value="employee.id">
                            {{ employee.label }}
                        </option>
                    </select>
                    <p v-if="entryForm.errors.employee_id" class="mt-1 text-sm text-red-600">{{ entryForm.errors.employee_id }}</p>
                </div>
                <div v-if="!editingEntry" class="md:col-span-2">
                    <DutyDateSelector
                        v-model:date-mode="entryForm.date_mode"
                        v-model:duty-date="entryForm.duty_date"
                        v-model:from-date="entryForm.from_date"
                        v-model:to-date="entryForm.to_date"
                        v-model:duty-dates="entryForm.duty_dates"
                        v-model:skip-weekends="entryForm.skip_weekends"
                        allow-single
                        :errors="entryForm.errors"
                    />
                </div>
                <UiInput v-else v-model="entryForm.duty_date" label="Duty date" type="date" required :error="entryForm.errors.duty_date" />
                <UiInput v-model.number="entryForm.grace_minutes" label="Grace minutes" type="number" min="0" max="180" :error="entryForm.errors.grace_minutes" />
                <UiInput v-model="entryForm.duty_start_time" label="Duty start" type="time" required :error="entryForm.errors.duty_start_time" />
                <UiInput v-model="entryForm.duty_end_time" label="Duty end" type="time" required :error="entryForm.errors.duty_end_time" />
                <div class="md:col-span-2">
                    <UiInput v-model="entryForm.notes" label="Notes" :error="entryForm.errors.notes" />
                </div>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <UiButton type="button" variant="ghost" @click="closeModal">Cancel</UiButton>
                    <UiButton type="submit" :disabled="entryForm.processing">
                        {{ editingEntry ? 'Update duty' : 'Create duty' }}
                    </UiButton>
                </div>
            </form>
        </UiModal>
    </AppLayout>
</template>
