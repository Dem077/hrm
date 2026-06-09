<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

withDefaults(
    defineProps<{
        href?: string;
        external?: boolean;
        method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
        type?: 'button' | 'submit';
        variant?: Variant;
        size?: 'sm' | 'md';
        disabled?: boolean;
        block?: boolean;
    }>(),
    {
        external: false,
        method: 'get',
        type: 'button',
        variant: 'secondary',
        size: 'md',
        disabled: false,
        block: false,
    },
);

const variantClasses: Record<Variant, string> = {
    primary: 'bg-brand-600 text-white shadow-sm shadow-brand-600/20 hover:bg-brand-500 border-transparent',
    secondary:
        'bg-surface-elevated text-slate-700 border-slate-200 hover:bg-surface-muted hover:border-slate-300 dark:text-slate-200 dark:border-slate-700 dark:hover:border-slate-600',
    ghost:
        'bg-transparent text-slate-600 border-transparent hover:bg-surface-elevated hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100',
    danger:
        'bg-red-50 text-red-700 border-red-200 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900/60 dark:hover:bg-red-950/60',
};

const sizeClasses = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
};
</script>

<template>
    <a
        v-if="href && external"
        :href="href"
        :class="
            cn(
                'inline-flex items-center justify-center rounded-xl border font-medium transition disabled:cursor-not-allowed disabled:opacity-50',
                block && 'w-full',
                variantClasses[variant],
                sizeClasses[size],
            )
        "
    >
        <slot />
    </a>
    <Link
        v-else-if="href"
        :href="href"
        :method="method"
        as="button"
        :class="
            cn(
                'inline-flex items-center justify-center rounded-xl border font-medium transition disabled:cursor-not-allowed disabled:opacity-50',
                block && 'w-full',
                variantClasses[variant],
                sizeClasses[size],
            )
        "
        :disabled="disabled"
    >
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :disabled="disabled"
        :class="
            cn(
                'inline-flex items-center justify-center rounded-xl border font-medium transition disabled:cursor-not-allowed disabled:opacity-50',
                block && 'w-full',
                variantClasses[variant],
                sizeClasses[size],
            )
        "
    >
        <slot />
    </button>
</template>
