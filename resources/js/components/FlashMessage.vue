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
        class="mb-6 rounded-lg border px-4 py-3 text-sm"
        :class="
            tone === 'error'
                ? 'border-red-200 bg-red-50 text-red-800'
                : 'border-emerald-200 bg-emerald-50 text-emerald-800'
        "
    >
        {{ message }}
    </div>
</template>
