<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

type Option = {
    value: string | number;
    label: string;
};

const props = defineProps<{
    id?: string;
    label: string;
    modelValue?: string | number | null;
    options: Option[];
    placeholder?: string;
    emptyLabel?: string;
    selectedLabel?: string | null;
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
}>();

const root = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const dropdownRef = ref<HTMLElement | null>(null);
const isOpen = ref(false);
const query = ref('');
const dropdownStyle = ref({
    top: '0px',
    left: '0px',
    width: '0px',
});

const selectedOption = computed(() => {
    if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) {
        return null;
    }

    return props.options.find((option) => String(option.value) === String(props.modelValue)) ?? null;
});

const closedLabel = computed(
    () => selectedOption.value?.label ?? props.selectedLabel ?? props.emptyLabel ?? '',
);

const filteredOptions = computed(() => {
    const search = query.value.trim().toLowerCase();

    if (!search) {
        return props.options;
    }

    return props.options.filter((option) => option.label.toLowerCase().includes(search));
});

function updateDropdownPosition() {
    if (!inputRef.value) {
        return;
    }

    const rect = inputRef.value.getBoundingClientRect();

    dropdownStyle.value = {
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
    };
}

function open() {
    isOpen.value = true;
    query.value = closedLabel.value === (props.emptyLabel ?? '') ? '' : closedLabel.value;
    nextTick(updateDropdownPosition);
}

function close() {
    isOpen.value = false;
    query.value = '';
}

function select(value: string | number | null) {
    emit('update:modelValue', value);
    close();
}

function onInput(event: Event) {
    query.value = (event.target as HTMLInputElement).value;

    if (!isOpen.value) {
        isOpen.value = true;
    }

    nextTick(updateDropdownPosition);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        close();
        inputRef.value?.blur();
    }
}

function onClickOutside(event: MouseEvent) {
    const target = event.target as Node;

    if (root.value?.contains(target) || dropdownRef.value?.contains(target)) {
        return;
    }

    close();
}

watch(isOpen, (open) => {
    if (open) {
        nextTick(updateDropdownPosition);
        window.addEventListener('scroll', updateDropdownPosition, true);
        window.addEventListener('resize', updateDropdownPosition);
        return;
    }

    window.removeEventListener('scroll', updateDropdownPosition, true);
    window.removeEventListener('resize', updateDropdownPosition);
});

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => {
    document.removeEventListener('mousedown', onClickOutside);
    window.removeEventListener('scroll', updateDropdownPosition, true);
    window.removeEventListener('resize', updateDropdownPosition);
});
</script>

<template>
    <div ref="root" class="relative">
        <label :for="id" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label }}</label>
        <input
            :id="id"
            ref="inputRef"
            :value="isOpen ? query : closedLabel"
            type="text"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="isOpen"
            :placeholder="placeholder ?? 'Search...'"
            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
            @focus="open"
            @input="onInput"
            @keydown="onKeydown"
        />

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="dropdownRef"
                :style="dropdownStyle"
                class="fixed z-[200] max-h-60 overflow-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-surface-elevated"
                role="listbox"
            >
                <button
                    v-if="emptyLabel"
                    type="button"
                    class="block w-full px-3.5 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-surface-muted"
                    :class="modelValue === '' || modelValue === null || modelValue === undefined ? 'bg-brand-50 text-brand-800 dark:bg-brand-600/15 dark:text-brand-300' : ''"
                    @mousedown.prevent
                    @click="select('')"
                >
                    {{ emptyLabel }}
                </button>

                <button
                    v-for="option in filteredOptions"
                    :key="option.value"
                    type="button"
                    class="block w-full px-3.5 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-surface-muted"
                    :class="String(option.value) === String(modelValue) ? 'bg-brand-50 text-brand-800 dark:bg-brand-600/15 dark:text-brand-300' : ''"
                    @mousedown.prevent
                    @click="select(option.value)"
                >
                    {{ option.label }}
                </button>

                <p v-if="filteredOptions.length === 0" class="px-3.5 py-2 text-sm text-slate-500 dark:text-slate-400">
                    No matches found.
                </p>
            </div>
        </Teleport>

        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
