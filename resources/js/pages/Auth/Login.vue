<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import ThemeToggle from '@/components/ThemeToggle.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';

const form = useForm({
    login: '',
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

        <div class="relative hidden w-1/2 overflow-hidden bg-sidebar lg:flex lg:items-center lg:justify-center">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.15),transparent_45%),radial-gradient(circle_at_bottom_left,rgba(14,165,233,0.1),transparent_40%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.2),transparent_45%),radial-gradient(circle_at_bottom_left,rgba(14,165,233,0.12),transparent_40%)]" />
            <div class="relative flex h-28 w-28 items-center justify-center rounded-3xl bg-brand-600 text-5xl font-bold text-white shadow-2xl shadow-brand-600/30">
                H
            </div>
        </div>

        <div class="flex w-full items-center justify-center px-4 py-10 pb-[max(2.5rem,env(safe-area-inset-bottom))] lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-8 flex flex-col items-center lg:hidden">
                    <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-brand-600 text-4xl font-bold text-white shadow-xl shadow-brand-600/30">
                        H
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-surface p-6 shadow-xl shadow-slate-200/60 dark:border-slate-800 dark:shadow-black/30 sm:p-8">
                    <div class="mb-8 text-center lg:text-left">
                        <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Sign in</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Sign in with your staff ID, email address, or national ID.
                        </p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <UiInput
                            id="login"
                            v-model="form.login"
                            label="Staff ID, email address, or national ID"
                            type="text"
                            required
                            autocomplete="username"
                            :error="form.errors.login"
                        />

                        <UiInput
                            id="password"
                            v-model="form.password"
                            label="Password"
                            type="password"
                            required
                            autocomplete="current-password"
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
                </div>
            </div>
        </div>
    </div>
</template>
