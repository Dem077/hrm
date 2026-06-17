<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import { appInitial } from '@/composables/useAppBranding';
import type { AppBranding } from '@/types/branding';

const props = withDefaults(
    defineProps<{
        size?: 'sm' | 'md' | 'lg';
        branding?: AppBranding | null;
    }>(),
    {
        size: 'md',
        branding: null,
    },
);

const page = usePage<{ branding: AppBranding }>();

const branding = computed(() => props.branding ?? page.props.branding);
const initial = computed(() => appInitial(branding.value));

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-9 w-9 rounded-lg text-xs';
        case 'lg':
            return 'h-28 w-28 rounded-3xl text-5xl';
        default:
            return 'h-10 w-10 rounded-xl text-sm';
    }
});
</script>

<template>
    <div
        v-if="branding.logo_url"
        class="flex shrink-0 items-center justify-center overflow-hidden bg-white ring-1 ring-slate-200 dark:bg-surface-elevated dark:ring-slate-700"
        :class="sizeClasses"
    >
        <img :src="branding.logo_url" :alt="`${branding.app_name} logo`" class="h-full w-full object-contain p-1" />
    </div>
    <div
        v-else
        class="flex shrink-0 items-center justify-center bg-brand-600 font-bold text-white shadow-lg shadow-brand-600/20"
        :class="sizeClasses"
    >
        {{ initial }}
    </div>
</template>
