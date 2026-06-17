<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiDateInput from '@/components/ui/UiDateInput.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiSearchableSelect from '@/components/ui/UiSearchableSelect.vue';

const props = defineProps<{
    open: boolean;
    employees: Array<{ id: number; label: string }>;
    canSelectEmployee: boolean;
    defaults: {
        employee_id: number | null;
        duty_date: string;
    };
}>();

const emit = defineEmits<{
    close: [];
}>();

const form = useForm({
    employee_id: null as number | null,
    duty_date: '',
    punched_at: '09:00',
    punch_state: 0,
    reason: '',
});

const employeeOptions = computed(() =>
    props.employees.map((employee) => ({
        value: employee.id,
        label: employee.label,
    })),
);

const selectedEmployeeLabel = computed(() => {
    if (!form.employee_id) {
        return null;
    }

    return props.employees.find((employee) => employee.id === form.employee_id)?.label ?? null;
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.reset();
        form.employee_id = props.defaults.employee_id;
        form.duty_date = props.defaults.duty_date;
        form.punched_at = '09:00';
        form.punch_state = 0;
        form.reason = '';
        form.clearErrors();
    },
);

function submit() {
    form.post('/attendance-sheet/manual-punches', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <UiModal
        :open="open"
        title="Add manual punch"
        description="Record a check-in or check-out with a required reason."
        @close="emit('close')"
    >
        <form class="grid gap-4" @submit.prevent="submit">
            <UiSearchableSelect
                v-if="canSelectEmployee"
                v-model="form.employee_id"
                label="Employee"
                placeholder="Search by name or staff ID..."
                :options="employeeOptions"
                :selected-label="selectedEmployeeLabel"
                :error="form.errors.employee_id"
            />
            <div v-else-if="selectedEmployeeLabel">
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Employee</label>
                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300">
                    {{ selectedEmployeeLabel }}
                </p>
            </div>

            <UiDateInput v-model="form.duty_date" label="Date" required :error="form.errors.duty_date" />

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Punch type</label>
                <select
                    v-model.number="form.punch_state"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-surface-elevated dark:text-white"
                >
                    <option :value="0">Check in</option>
                    <option :value="1">Check out</option>
                </select>
                <p v-if="form.errors.punch_state" class="mt-1 text-sm text-red-600">{{ form.errors.punch_state }}</p>
            </div>

            <UiInput v-model="form.punched_at" label="Punch time" type="time" required :error="form.errors.punched_at" />

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Reason</label>
                <textarea
                    v-model="form.reason"
                    rows="3"
                    required
                    placeholder="Explain why this punch is being added manually..."
                    class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                />
                <p v-if="form.errors.reason" class="mt-1 text-sm text-red-600">{{ form.errors.reason }}</p>
            </div>

            <div class="flex justify-end gap-2">
                <UiButton type="button" variant="ghost" @click="emit('close')">Cancel</UiButton>
                <UiButton type="submit" :disabled="form.processing">Add punch</UiButton>
            </div>
        </form>
    </UiModal>
</template>
