<script setup lang="ts">
defineProps<{
    tone?: 'success' | 'error' | 'info';
    message: string;
}>();

const tones = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300',
    error: 'border-red-200 bg-red-50 text-red-800 dark:border-red-800/60 dark:bg-red-950/40 dark:text-red-300',
    info: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800/60 dark:bg-sky-950/40 dark:text-sky-300',
};
</script>

<template>
    <div class="rounded-xl border px-4 py-3 text-sm" :class="tones[tone ?? 'info']">
        {{ message }}
    </div>
</template>
