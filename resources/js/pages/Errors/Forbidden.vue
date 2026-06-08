<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import UiButton from '@/components/ui/UiButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    message?: string;
}>();
</script>

<template>
    <Head title="Access denied" />

    <AppLayout title="Access denied" description="You do not have permission to view this page.">
        <div class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-surface p-8 text-center shadow-sm dark:border-slate-800">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m0 0v2m0-2h2m-2 0H10m4-6V9a4 4 0 10-8 0v2m12 0H6a2 2 0 00-2 2v6a2 2 0 002 2h12a2 2 0 002-2v-6a2 2 0 00-2-2z" />
                </svg>
            </div>
            <h2 class="mt-5 text-xl font-semibold text-slate-900 dark:text-white">Access denied</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ message ?? 'You do not have permission to access this page.' }}
            </p>
            <div class="mt-6">
                <UiButton href="/" variant="primary">Go to dashboard</UiButton>
            </div>
            <p class="mt-4 text-xs text-slate-400">
                Need access?
                <Link href="/roles" class="text-brand-600 hover:underline dark:text-brand-400">Ask an administrator to update your role.</Link>
            </p>
        </div>
    </AppLayout>
</template>
