<script setup lang="ts">
defineProps<{
    id?: string;
    label: string;
    modelValue?: string | null;
    error?: string;
    hint?: string;
    required?: boolean;
    placeholder?: string;
    rows?: number;
}>();

defineEmits<{
    'update:modelValue': [value: string | null];
}>();
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label }}</label>
        <textarea
            :id="id"
            :value="modelValue ?? ''"
            :required="required"
            :placeholder="placeholder"
            :rows="rows ?? 3"
            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
            @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
        <p v-if="hint && !error" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
