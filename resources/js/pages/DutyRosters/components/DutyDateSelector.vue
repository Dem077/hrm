<script setup lang="ts">
import { computed } from 'vue';

import UiInput from '@/components/ui/UiInput.vue';
import UiMultiDateCalendar from '@/components/ui/UiMultiDateCalendar.vue';

export type DutyDateMode = 'single' | 'range' | 'specific';

const props = withDefaults(
    defineProps<{
        dateMode: DutyDateMode;
        dutyDate?: string;
        fromDate: string;
        toDate: string;
        dutyDates: string[];
        skipWeekends: boolean;
        allowSingle?: boolean;
        errors?: Record<string, string>;
    }>(),
    {
        allowSingle: false,
        dutyDate: '',
        errors: () => ({}),
    },
);

const emit = defineEmits<{
    'update:dateMode': [value: DutyDateMode];
    'update:dutyDate': [value: string];
    'update:fromDate': [value: string];
    'update:toDate': [value: string];
    'update:dutyDates': [value: string[]];
    'update:skipWeekends': [value: boolean];
}>();

const modes = computed<Array<{ value: DutyDateMode; label: string }>>(() => [
    ...(props.allowSingle ? [{ value: 'single' as const, label: 'Single date' }] : []),
    { value: 'range', label: 'Date range' },
    { value: 'specific', label: 'Specific dates' },
]);
</script>

<template>
    <div class="grid gap-4">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">Date selection</label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="mode in modes"
                    :key="mode.value"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-sm font-medium transition"
                    :class="
                        dateMode === mode.value
                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-400 dark:bg-brand-950 dark:text-brand-300'
                            : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400'
                    "
                    @click="emit('update:dateMode', mode.value)"
                >
                    {{ mode.label }}
                </button>
            </div>
        </div>

        <UiInput
            v-if="dateMode === 'single'"
            :model-value="dutyDate"
            label="Duty date"
            type="date"
            required
            :error="errors.duty_date"
            @update:model-value="emit('update:dutyDate', $event)"
        />

        <template v-else-if="dateMode === 'range'">
            <div class="grid gap-4 md:grid-cols-2">
                <UiInput
                    :model-value="fromDate"
                    label="From date"
                    type="date"
                    required
                    :error="errors.from_date"
                    @update:model-value="emit('update:fromDate', $event)"
                />
                <UiInput
                    :model-value="toDate"
                    label="To date"
                    type="date"
                    required
                    :error="errors.to_date"
                    @update:model-value="emit('update:toDate', $event)"
                />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input
                    :checked="skipWeekends"
                    type="checkbox"
                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    @change="emit('update:skipWeekends', ($event.target as HTMLInputElement).checked)"
                />
                Skip weekends (Fri &amp; Sat)
            </label>
        </template>

        <UiMultiDateCalendar
            v-else
            :model-value="dutyDates"
            :error="errors.duty_dates"
            @update:model-value="emit('update:dutyDates', $event)"
        />
    </div>
</template>
