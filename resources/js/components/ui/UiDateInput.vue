<script setup lang="ts">
import { ref, watch } from 'vue';

import { displayToIso, formatDate } from '@/lib/format';

const props = withDefaults(
    defineProps<{
        id?: string;
        label: string;
        modelValue?: string | number | null;
        error?: string;
        hint?: string;
        disabled?: boolean;
        required?: boolean;
        placeholder?: string;
    }>(),
    {
        placeholder: 'DD/MM/YYYY',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    input: [];
}>();

const display = ref('');
const pickerRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.modelValue,
    (value) => {
        display.value = value ? formatDate(String(value)) : '';
    },
    { immediate: true },
);

function commitDisplay() {
    if (!display.value.trim()) {
        emit('update:modelValue', '');
        emit('input');

        return;
    }

    const iso = displayToIso(display.value);

    if (iso) {
        display.value = formatDate(iso);
        emit('update:modelValue', iso);
        emit('input');

        return;
    }

    if (props.modelValue) {
        display.value = formatDate(String(props.modelValue));
    }
}

function onPickerChange(event: Event) {
    const value = (event.target as HTMLInputElement).value;

    emit('update:modelValue', value);
    emit('input');
}

function openPicker() {
    if (props.disabled) {
        return;
    }

    pickerRef.value?.showPicker?.();
}
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label }}</label>
        <div class="relative flex items-center">
            <input
                :id="id"
                v-model="display"
                type="text"
                inputmode="numeric"
                :placeholder="placeholder"
                :readonly="disabled"
                :required="required"
                class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-11 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 read-only:bg-slate-50 read-only:text-slate-500 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100 dark:read-only:bg-surface-muted"
                @blur="commitDisplay"
                @keydown.enter.prevent="commitDisplay"
            />
            <button
                type="button"
                class="absolute right-1.5 rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-surface-muted dark:hover:text-slate-200"
                :disabled="disabled"
                title="Open calendar"
                @click="openPicker"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
            </button>
            <input
                ref="pickerRef"
                type="date"
                tabindex="-1"
                aria-hidden="true"
                class="pointer-events-none absolute h-0 w-0 opacity-0"
                :value="modelValue ?? ''"
                @change="onPickerChange"
            />
        </div>
        <p v-if="hint && !error" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
