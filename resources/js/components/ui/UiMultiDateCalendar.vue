<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import { formatDate } from '@/lib/format';

const props = withDefaults(
    defineProps<{
        modelValue: string[];
        maxDates?: number;
        error?: string;
    }>(),
    {
        maxDates: 31,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const stagedDates = ref<string[]>([]);
const viewMonth = ref(startOfMonth(new Date()));

const weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const monthLabel = computed(() =>
    new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }).format(viewMonth.value),
);

const todayIso = localTodayIso();

const remainingSlots = computed(() => Math.max(0, props.maxDates - props.modelValue.length));

const calendarDays = computed(() => {
    const year = viewMonth.value.getFullYear();
    const month = viewMonth.value.getMonth();
    const firstDay = new Date(year, month, 1);
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const startOffset = (firstDay.getDay() + 6) % 7;
    const days: Array<{ iso: string | null; day: number }> = [];

    for (let index = 0; index < startOffset; index++) {
        days.push({ iso: null, day: 0 });
    }

    for (let day = 1; day <= daysInMonth; day++) {
        days.push({ iso: toIsoDate(year, month, day), day });
    }

    return days;
});

watch(
    () => props.modelValue,
    () => {
        stagedDates.value = stagedDates.value.filter((date) => !props.modelValue.includes(date));
    },
);

function startOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function localTodayIso(): string {
    const date = new Date();

    return toIsoDate(date.getFullYear(), date.getMonth(), date.getDate());
}

function toIsoDate(year: number, month: number, day: number): string {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function previousMonth() {
    viewMonth.value = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth() - 1, 1);
}

function nextMonth() {
    viewMonth.value = new Date(viewMonth.value.getFullYear(), viewMonth.value.getMonth() + 1, 1);
}

function isStaged(iso: string): boolean {
    return stagedDates.value.includes(iso);
}

function isAdded(iso: string): boolean {
    return props.modelValue.includes(iso);
}

function canStage(iso: string): boolean {
    if (isAdded(iso)) {
        return false;
    }

    if (isStaged(iso)) {
        return true;
    }

    return remainingSlots.value > stagedDates.value.length;
}

function toggleDate(iso: string) {
    if (isAdded(iso)) {
        return;
    }

    if (isStaged(iso)) {
        stagedDates.value = stagedDates.value.filter((date) => date !== iso);

        return;
    }

    if (stagedDates.value.length >= remainingSlots.value) {
        return;
    }

    stagedDates.value = [...stagedDates.value, iso].sort();
}

function clearStaged() {
    stagedDates.value = [];
}

function addStagedDates() {
    if (stagedDates.value.length === 0) {
        return;
    }

    const merged = [...new Set([...props.modelValue, ...stagedDates.value])].sort();
    emit('update:modelValue', merged.slice(0, props.maxDates));
    stagedDates.value = [];
}

function removeDate(iso: string) {
    emit(
        'update:modelValue',
        props.modelValue.filter((date) => date !== iso),
    );
}

function dayClass(iso: string): string {
    if (isAdded(iso)) {
        return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800';
    }

    if (isStaged(iso)) {
        return 'bg-brand-500 text-white ring-2 ring-brand-300 dark:ring-brand-400';
    }

    if (iso === todayIso) {
        return 'bg-slate-100 text-slate-900 ring-1 ring-slate-300 dark:bg-slate-800 dark:text-white dark:ring-slate-600';
    }

    return 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800';
}
</script>

<template>
    <div class="grid gap-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-surface-elevated">
            <div class="mb-4 flex items-center justify-between gap-2">
                <UiButton type="button" size="sm" variant="ghost" @click="previousMonth">Previous</UiButton>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ monthLabel }}</p>
                <UiButton type="button" size="sm" variant="ghost" @click="nextMonth">Next</UiButton>
            </div>

            <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs font-medium text-slate-500 dark:text-slate-400">
                <span v-for="label in weekdayLabels" :key="label">{{ label }}</span>
            </div>

            <div class="grid grid-cols-7 gap-1">
                <span v-for="(cell, index) in calendarDays" :key="`${cell.iso ?? 'blank'}-${index}`" class="aspect-square">
                    <button
                        v-if="cell.iso"
                        type="button"
                        class="flex h-full w-full items-center justify-center rounded-lg text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40"
                        :class="dayClass(cell.iso)"
                        :disabled="!canStage(cell.iso)"
                        :title="isAdded(cell.iso) ? 'Already added' : undefined"
                        @click="toggleDate(cell.iso)"
                    >
                        {{ cell.day }}
                    </button>
                </span>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    <span v-if="stagedDates.length > 0">{{ stagedDates.length }} date(s) selected on calendar</span>
                    <span v-else>Click dates on the calendar to select them</span>
                    <span class="mx-1">·</span>
                    {{ modelValue.length }} / {{ maxDates }} added
                </p>
                <div class="flex gap-2">
                    <UiButton type="button" size="sm" variant="ghost" :disabled="stagedDates.length === 0" @click="clearStaged">
                        Clear
                    </UiButton>
                    <UiButton type="button" size="sm" :disabled="stagedDates.length === 0" @click="addStagedDates">
                        Add dates
                    </UiButton>
                </div>
            </div>
        </div>

        <p v-if="error" class="text-xs text-red-600 dark:text-red-400">{{ error }}</p>

        <div v-if="modelValue.length > 0" class="flex flex-wrap gap-2">
            <span
                v-for="date in modelValue"
                :key="date"
                class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
            >
                {{ formatDate(date) }}
                <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="removeDate(date)">
                    ×
                </button>
            </span>
        </div>
        <p v-else class="text-sm text-slate-500 dark:text-slate-400">No dates added yet. Select dates on the calendar and press Add dates.</p>
    </div>
</template>
