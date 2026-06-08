<script setup lang="ts">
defineProps<{
    id?: string;
    label: string;
    modelValue?: string | number | null;
    type?: string;
    error?: string;
    hint?: string;
    readonly?: boolean;
    required?: boolean;
    placeholder?: string;
}>();

defineEmits<{
    'update:modelValue': [value: string | number | null];
}>();
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label }}</label>
        <input
            :id="id"
            :value="modelValue ?? ''"
            :type="type ?? 'text'"
            :readonly="readonly"
            :required="required"
            :placeholder="placeholder"
            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 read-only:bg-slate-50 read-only:text-slate-500 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100 dark:read-only:bg-surface-muted"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
        <p v-if="hint && !error" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
