<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const message = computed(() => page.props.flash.success || page.props.flash.error);
const tone = computed(() => (page.props.flash.error ? 'error' : 'success'));

let timeout: ReturnType<typeof setTimeout> | undefined;

watch(message, (value) => {
    if (timeout) {
        clearTimeout(timeout);
    }

    if (!value) {
        return;
    }

    timeout = setTimeout(() => {
        page.props.flash.success = undefined;
        page.props.flash.error = undefined;
    }, 5000);
});
</script>

<template>
    <div
        v-if="message"
        class="mb-6 flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm shadow-sm"
        :class="
            tone === 'error'
                ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-800/60 dark:bg-red-950/40 dark:text-red-300'
                : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300'
        "
    >
        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                :d="tone === 'error' ? 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'"
            />
        </svg>
        <span>{{ message }}</span>
    </div>
</template>
