<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';

type Option = {
    value: string;
    label: string;
};

const props = withDefaults(
    defineProps<{
        id?: string;
        label: string;
        modelValue?: string | null;
        options: Option[];
        placeholder?: string;
        emptyLabel?: string;
        error?: string;
        storeUrl: string;
        destroyUrl: (value: string) => string;
        createModalTitle?: string;
        manageModalTitle?: string;
        recordLabel?: string;
        canManage?: boolean;
    }>(),
    {
        placeholder: 'Search...',
        emptyLabel: 'Not specified',
        createModalTitle: 'Create option',
        manageModalTitle: 'Manage options',
        recordLabel: 'Option',
        canManage: true,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const root = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const dropdownRef = ref<HTMLElement | null>(null);
const isOpen = ref(false);
const query = ref('');
const createOpen = ref(false);
const manageOpen = ref(false);
const manageError = ref<string | undefined>();
const deletingValue = ref<string | null>(null);

const createForm = useForm({ name: '' });

const dropdownStyle = ref({
    top: '0px',
    left: '0px',
    width: '0px',
});

const selectedOption = computed(() => {
    if (!props.modelValue) {
        return null;
    }

    return props.options.find((option) => option.value === props.modelValue) ?? null;
});

const displayOptions = computed(() => {
    if (
        props.modelValue &&
        !props.options.some((option) => option.value === props.modelValue)
    ) {
        return [
            { value: props.modelValue, label: `${props.modelValue} (current)` },
            ...props.options,
        ];
    }

    return props.options;
});

const closedLabel = computed(() => {
    if (!props.modelValue) {
        return '';
    }

    return selectedOption.value?.label ?? props.modelValue;
});

const filteredOptions = computed(() => {
    const search = query.value.trim().toLowerCase();

    if (!search) {
        return displayOptions.value;
    }

    return displayOptions.value.filter((option) => option.label.toLowerCase().includes(search));
});

const canCreateFromQuery = computed(() => {
    if (!props.canManage) {
        return false;
    }

    const search = query.value.trim();

    if (search === '') {
        return false;
    }

    return !displayOptions.value.some((option) => option.label.toLowerCase() === search.toLowerCase());
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
    query.value = '';
    nextTick(updateDropdownPosition);
}

function close() {
    isOpen.value = false;
    query.value = '';
}

function select(value: string | null) {
    emit('update:modelValue', value ?? '');
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

function openCreate(prefill = '') {
    close();
    manageError.value = undefined;
    createForm.clearErrors();
    createForm.reset();
    createForm.name = prefill;
    createOpen.value = true;
}

function openManage() {
    close();
    manageError.value = undefined;
    manageOpen.value = true;
}

function createFromQuery() {
    openCreate(query.value.trim());
}

function submitCreate() {
    const created = createForm.name.trim();

    createForm.post(props.storeUrl, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
            if (created) {
                emit('update:modelValue', created);
            }
        },
    });
}

function removeOption(value: string) {
    manageError.value = undefined;

    if (!confirm(`Delete "${value}"?`)) {
        return;
    }

    deletingValue.value = value;

    router.delete(props.destroyUrl(value), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (props.modelValue === value) {
                emit('update:modelValue', '');
            }
            deletingValue.value = null;
        },
        onError: (errors) => {
            deletingValue.value = null;
            manageError.value =
                errors.nationality ??
                errors.name ??
                Object.values(errors)[0] ??
                `Unable to delete this ${props.recordLabel.toLowerCase()}.`;
        },
    });
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
        <div class="mb-1.5 flex items-center justify-between gap-2">
            <label :for="id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label }}</label>
        </div>

        <div class="relative flex items-stretch gap-1.5">
            <div class="relative min-w-0 flex-1">
                <input
                    :id="id"
                    ref="inputRef"
                    :value="isOpen ? query : closedLabel"
                    type="text"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-expanded="isOpen"
                    :placeholder="placeholder"
                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-3.5 pr-10 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                    @focus="onFocus"
                    @click="onClick"
                    @input="onInput"
                    @keydown="onKeydown"
                />
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </span>
            </div>

            <template v-if="canManage">
                <button
                    type="button"
                    class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-brand-700 focus:outline-none focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300 dark:hover:bg-surface-muted"
                    :title="`Create ${recordLabel.toLowerCase()}`"
                    :aria-label="`Create ${recordLabel.toLowerCase()}`"
                    @click="openCreate()"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-brand-700 focus:outline-none focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300 dark:hover:bg-surface-muted"
                    :title="`Manage ${recordLabel.toLowerCase()}s`"
                    :aria-label="`Manage ${recordLabel.toLowerCase()}s`"
                    @click="openManage"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8"
                            d="M16.862 4.487 18.549 2.8a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                        />
                    </svg>
                </button>
            </template>
        </div>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="dropdownRef"
                :style="dropdownStyle"
                class="fixed z-[400] max-h-72 overflow-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-surface-elevated"
                role="listbox"
            >
                <button
                    v-if="emptyLabel"
                    type="button"
                    class="block w-full px-3.5 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-surface-muted"
                    :class="!modelValue ? 'bg-brand-50 text-brand-800 dark:bg-brand-600/15 dark:text-brand-300' : ''"
                    @mousedown.prevent
                    @click="select(null)"
                >
                    {{ emptyLabel }}
                </button>

                <button
                    v-for="option in filteredOptions"
                    :key="option.value"
                    type="button"
                    class="block w-full px-3.5 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-surface-muted"
                    :class="option.value === modelValue ? 'bg-brand-50 text-brand-800 dark:bg-brand-600/15 dark:text-brand-300' : ''"
                    @mousedown.prevent
                    @click="select(option.value)"
                >
                    {{ option.label }}
                </button>

                <p
                    v-if="filteredOptions.length === 0 && !canCreateFromQuery"
                    class="px-3.5 py-2 text-sm text-slate-500 dark:text-slate-400"
                >
                    No matches found.
                </p>

                <div v-if="canCreateFromQuery" class="border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3.5 py-2.5 text-left text-sm font-medium text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-600/10"
                        @mousedown.prevent
                        @click="createFromQuery"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Create “{{ query.trim() }}”</span>
                    </button>
                </div>
            </div>
        </Teleport>

        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>

    <UiModal
        :open="createOpen"
        :title="createModalTitle"
        :description="`Add a new ${recordLabel.toLowerCase()} to this list.`"
        max-width="md"
        @close="createOpen = false"
    >
        <form class="space-y-4" @submit.prevent="submitCreate">
            <UiInput
                v-model="createForm.name"
                :label="recordLabel"
                :placeholder="`New ${recordLabel.toLowerCase()}`"
                required
                :error="createForm.errors.name"
            />
            <div class="flex justify-end gap-2">
                <UiButton type="button" variant="ghost" @click="createOpen = false">Cancel</UiButton>
                <UiButton type="submit" :disabled="createForm.processing">
                    {{ createForm.processing ? 'Creating…' : 'Create' }}
                </UiButton>
            </div>
        </form>
    </UiModal>

    <UiModal
        :open="manageOpen"
        :title="manageModalTitle"
        :description="`Delete unused ${recordLabel.toLowerCase()}s from the list.`"
        max-width="md"
        @close="manageOpen = false"
    >
        <div class="space-y-3">
            <p v-if="manageError" class="text-sm text-red-600 dark:text-red-400">{{ manageError }}</p>

            <div
                v-if="options.length === 0"
                class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
            >
                No {{ recordLabel.toLowerCase() }}s yet. Use + to create one.
            </div>

            <ul v-else class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                <li
                    v-for="option in options"
                    :key="option.value"
                    class="flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm"
                >
                    <span class="min-w-0 truncate font-medium text-slate-800 dark:text-slate-100">{{ option.label }}</span>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                        :disabled="deletingValue === option.value"
                        :title="`Delete ${option.label}`"
                        :aria-label="`Delete ${option.label}`"
                        @click="removeOption(option.value)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                            />
                        </svg>
                    </button>
                </li>
            </ul>

            <div class="flex justify-end">
                <UiButton type="button" variant="ghost" @click="manageOpen = false">Done</UiButton>
            </div>
        </div>
    </UiModal>
</template>
