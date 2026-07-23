<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

export type GradeOption = {
    id: number;
    label: string;
    path_label: string;
    context_label: string;
    group_name: string | null;
    group_code: string | null;
    group_sort?: number;
    node_name: string | null;
    node_path?: string | null;
    level_label: string | null;
    section_label?: string;
    is_active?: boolean;
};

const props = withDefaults(
    defineProps<{
        id?: string;
        label?: string;
        modelValue?: string | number | null;
        options: GradeOption[];
        error?: string;
        emptyLabel?: string;
        placeholder?: string;
    }>(),
    {
        label: 'Grade',
        emptyLabel: 'Unassigned',
        placeholder: 'Search by grade, title, or department…',
    },
);

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

const selected = computed(() => {
    if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) {
        return null;
    }

    return props.options.find((option) => String(option.id) === String(props.modelValue)) ?? null;
});

const closedLabel = computed(() => selected.value?.label ?? '');

const filteredOptions = computed(() => {
    const search = query.value.trim().toLowerCase();

    if (!search) {
        return props.options;
    }

    return props.options.filter((option) => {
        const haystack = [
            option.label,
            option.context_label,
            option.path_label,
            option.group_name,
            option.node_name,
            option.node_path,
            option.level_label,
            option.section_label,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(search);
    });
});

const groupedOptions = computed(() => {
    const groups: Array<{
        key: string;
        title: string;
        sections: Array<{
            key: string;
            title: string;
            options: GradeOption[];
        }>;
    }> = [];

    for (const option of filteredOptions.value) {
        const groupKey = option.group_code || option.group_name || 'structure';
        const groupTitle = option.group_name || 'Company structure';
        const sectionKey = [option.node_path || '', option.level_label || ''].join('|') || option.section_label || groupKey;
        const sectionTitle = option.section_label || option.level_label || option.node_path || groupTitle;

        let group = groups.find((item) => item.key === groupKey);

        if (!group) {
            group = { key: groupKey, title: groupTitle, sections: [] };
            groups.push(group);
        }

        let section = group.sections.find((item) => item.key === sectionKey);

        if (!section) {
            section = { key: sectionKey, title: sectionTitle, options: [] };
            group.sections.push(section);
        }

        section.options.push(option);
    }

    return groups;
});

function updateDropdownPosition() {
    if (!inputRef.value) {
        return;
    }

    const rect = inputRef.value.getBoundingClientRect();
    const width = Math.max(rect.width, 360);

    dropdownStyle.value = {
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${Math.min(width, window.innerWidth - 16)}px`,
    };
}

function open() {
    isOpen.value = true;
    query.value = '';
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

function onFocus() {
    open();
}

function onClick() {
    if (!isOpen.value) {
        open();
    }
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
            :placeholder="selected ? selected.label : placeholder"
            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
            @focus="onFocus"
            @click="onClick"
            @input="onInput"
            @keydown="onKeydown"
        />

        <p v-if="!isOpen && selected?.context_label" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
            {{ selected.context_label }}
        </p>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="dropdownRef"
                :style="dropdownStyle"
                class="fixed z-[400] max-h-80 overflow-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-surface-elevated"
                role="listbox"
            >
                <button
                    type="button"
                    class="block w-full px-3.5 py-2.5 text-left text-sm text-slate-600 transition hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-surface-muted"
                    :class="modelValue === '' || modelValue === null || modelValue === undefined ? 'bg-brand-50 text-brand-800 dark:bg-brand-600/15 dark:text-brand-300' : ''"
                    @mousedown.prevent
                    @click="select('')"
                >
                    {{ emptyLabel }}
                </button>

                <div v-for="group in groupedOptions" :key="group.key" class="border-t border-slate-100 dark:border-slate-800">
                    <div class="sticky top-0 z-10 bg-slate-100 px-3.5 py-2 dark:bg-slate-900">
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                            {{ group.title }}
                        </p>
                    </div>

                    <div v-for="section in group.sections" :key="section.key" class="pb-1">
                        <p class="px-3.5 pb-1 pt-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                            {{ section.title }}
                        </p>

                        <button
                            v-for="option in section.options"
                            :key="option.id"
                            type="button"
                            class="block w-full px-3.5 py-2 pl-5 text-left transition hover:bg-slate-50 dark:hover:bg-surface-muted"
                            :class="String(option.id) === String(modelValue) ? 'bg-brand-50 dark:bg-brand-600/15' : ''"
                            @mousedown.prevent
                            @click="select(option.id)"
                        >
                            <span
                                class="block text-sm font-medium text-slate-900 dark:text-slate-100"
                                :class="String(option.id) === String(modelValue) ? 'text-brand-800 dark:text-brand-300' : ''"
                            >
                                {{ option.label }}
                            </span>
                        </button>
                    </div>
                </div>

                <p v-if="groupedOptions.length === 0" class="px-3.5 py-3 text-sm text-slate-500 dark:text-slate-400">
                    No grades match your search.
                </p>
            </div>
        </Teleport>

        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
