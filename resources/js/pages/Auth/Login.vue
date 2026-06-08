<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import ThemeToggle from '@/components/ThemeToggle.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login');
}
</script>

<template>
    <Head title="Sign In" />

    <div class="relative flex min-h-screen bg-canvas">
        <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
            <ThemeToggle />
        </div>

        <div class="relative hidden w-1/2 overflow-hidden bg-sidebar lg:flex lg:flex-col lg:justify-between">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.15),transparent_45%),radial-gradient(circle_at_bottom_left,rgba(14,165,233,0.1),transparent_40%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.2),transparent_45%),radial-gradient(circle_at_bottom_left,rgba(14,165,233,0.12),transparent_40%)]" />
            <div class="relative px-12 pt-12">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-lg font-semibold shadow-lg shadow-brand-600/30">
                    H
                </div>
                <h1 class="mt-8 max-w-md text-4xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    Manage attendance devices with confidence.
                </h1>
                <p class="mt-4 max-w-md text-base leading-7 text-slate-600 dark:text-slate-400">
                    Connect ZKT biometric machines, monitor device health, and sync punch logs into one secure workspace.
                </p>
            </div>
            <div class="relative border-t border-sidebar-border px-12 py-8 text-sm text-slate-500">
                Admin-managed access only. New accounts are created by your system administrator.
            </div>
        </div>

        <div class="flex w-full items-center justify-center px-4 py-10 pb-[max(2.5rem,env(safe-area-inset-bottom))] lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-lg font-semibold text-white">
                        H
                    </div>
                    <h2 class="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">Sign in to HRM</h2>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-surface p-6 shadow-xl shadow-slate-200/60 dark:border-slate-800 dark:shadow-black/30 sm:p-8">
                    <div class="mb-8 hidden lg:block">
                        <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Welcome back</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Sign in with your administrator-provided account.</p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <UiInput
                            id="email"
                            v-model="form.email"
                            label="Email address"
                            type="email"
                            required
                            :error="form.errors.email"
                        />

                        <UiInput
                            id="password"
                            v-model="form.password"
                            label="Password"
                            type="password"
                            required
                            :error="form.errors.password"
                        />

                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="rounded border-slate-300 bg-white text-brand-600 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-surface-elevated dark:text-brand-500"
                            />
                            Keep me signed in
                        </label>

                        <UiButton type="submit" variant="primary" block :disabled="form.processing">
                            {{ form.processing ? 'Signing in...' : 'Sign in' }}
                        </UiButton>
                    </form>

                    <p class="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-center text-xs leading-5 text-slate-500 dark:bg-surface-elevated">
                        Self-registration is disabled. Contact your administrator if you need access.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
