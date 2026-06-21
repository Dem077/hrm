<script setup lang="ts">
import { computed, ref } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';

const props = withDefaults(
    defineProps<{
        previewUrl?: string | null;
        name?: string;
        error?: string;
        canRemove?: boolean;
        markedForRemoval?: boolean;
        pendingFileName?: string | null;
        hint?: string;
    }>(),
    {
        previewUrl: null,
        name: '',
        error: undefined,
        canRemove: false,
        markedForRemoval: false,
        pendingFileName: null,
        hint: 'Square photos work best. PNG, JPG, or WebP up to 2 MB.',
    },
);

const emit = defineEmits<{
    select: [file: File];
    remove: [];
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

const initials = computed(() => (props.name.trim() || '?').charAt(0).toUpperCase());
const hasPreview = computed(() => Boolean(props.previewUrl) && !props.markedForRemoval);

const statusMessage = computed(() => {
    if (props.markedForRemoval) {
        return 'Photo will be removed when you save.';
    }

    if (props.pendingFileName) {
        return `New photo selected: ${props.pendingFileName}`;
    }

    if (hasPreview.value) {
        return 'Current photo will be kept unless you upload a new one.';
    }

    return 'No profile photo yet.';
});

function openPicker() {
    inputRef.value?.click();
}

function onFileChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file) {
        emit('select', file);
    }

    if (inputRef.value) {
        inputRef.value.value = '';
    }
}

function onDragOver(event: DragEvent) {
    event.preventDefault();
    isDragging.value = true;
}

function onDragLeave() {
    isDragging.value = false;
}

function onDrop(event: DragEvent) {
    event.preventDefault();
    isDragging.value = false;

    const file = event.dataTransfer?.files?.[0];

    if (file?.type.startsWith('image/')) {
        emit('select', file);
    }
}
</script>

<template>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <div class="flex shrink-0 flex-col items-center gap-3 lg:w-40">
            <button
                type="button"
                class="group relative"
                aria-label="Upload profile photo"
                @click="openPicker"
            >
                <div
                    class="relative flex h-32 w-32 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-brand-500/15 via-brand-600/10 to-slate-100 ring-4 ring-white shadow-lg shadow-slate-200/80 transition group-hover:ring-brand-500/20 dark:from-brand-500/20 dark:via-brand-950/30 dark:to-surface-elevated dark:ring-surface dark:shadow-black/30 dark:group-hover:ring-brand-500/30"
                    :class="markedForRemoval ? 'opacity-60 grayscale' : ''"
                >
                    <img
                        v-if="hasPreview"
                        :src="previewUrl!"
                        :alt="`${name || 'Employee'} profile photo`"
                        class="h-full w-full object-cover"
                    />
                    <span v-else class="text-4xl font-semibold text-brand-700 dark:text-brand-300">{{ initials }}</span>

                    <div
                        class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-slate-900/55 text-white opacity-0 transition group-hover:opacity-100"
                    >
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                            />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="text-xs font-medium">Change</span>
                    </div>
                </div>
            </button>

            <p class="text-center text-xs text-slate-500 dark:text-slate-400">Click photo to replace</p>
        </div>

        <div class="min-w-0 flex-1 space-y-4">
            <div
                role="button"
                tabindex="0"
                class="relative rounded-2xl border-2 border-dashed px-6 py-8 text-center transition"
                :class="
                    isDragging
                        ? 'border-brand-500 bg-brand-50/80 dark:border-brand-400 dark:bg-brand-950/30'
                        : error
                          ? 'border-red-300 bg-red-50/50 dark:border-red-900/60 dark:bg-red-950/20'
                          : 'border-slate-200 bg-slate-50/70 hover:border-brand-400 hover:bg-brand-50/40 dark:border-slate-700 dark:bg-surface-muted/50 dark:hover:border-brand-500/50 dark:hover:bg-brand-950/20'
                "
                @click="openPicker"
                @keydown.enter.prevent="openPicker"
                @keydown.space.prevent="openPicker"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
            >
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-slate-200 dark:bg-surface-elevated dark:ring-slate-700">
                    <svg class="h-6 w-6 text-brand-600 dark:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                </div>

                <p class="mt-4 text-sm font-medium text-slate-900 dark:text-slate-100">
                    <span class="text-brand-700 dark:text-brand-300">Choose a file</span>
                    <span class="text-slate-500 dark:text-slate-400"> or drag and drop here</span>
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
            </div>

            <input
                ref="inputRef"
                type="file"
                accept="image/png,image/jpeg,image/jpg,image/webp"
                class="sr-only"
                @change="onFileChange"
            />

            <div class="flex flex-wrap items-center gap-2">
                <UiButton type="button" size="sm" variant="secondary" @click="openPicker">Upload photo</UiButton>
                <UiButton v-if="canRemove" type="button" size="sm" variant="ghost" @click="emit('remove')">Remove photo</UiButton>
            </div>

            <p
                class="text-sm"
                :class="
                    markedForRemoval
                        ? 'text-amber-700 dark:text-amber-300'
                        : pendingFileName
                          ? 'text-brand-700 dark:text-brand-300'
                          : 'text-slate-500 dark:text-slate-400'
                "
            >
                {{ statusMessage }}
            </p>

            <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
        </div>
    </div>
</template>
