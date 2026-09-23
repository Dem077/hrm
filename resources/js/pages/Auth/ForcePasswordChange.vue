<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppBrandMark from '@/components/AppBrandMark.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import { loginPanelGradient } from '@/composables/useAppBranding';
import { useTheme } from '@/composables/useTheme';
import type { AppBranding } from '@/types/branding';

const page = usePage<{ branding: AppBranding }>();
const branding = computed(() => page.props.branding);
const { isDark } = useTheme();

const panelGradient = computed(() => ({
    background: loginPanelGradient(branding.value, isDark.value),
}));

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put('/password/force-change');
}
</script>

<template>
    <Head title="Change password" />

    <div class="relative flex min-h-screen bg-canvas">
        <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
            <ThemeToggle />
        </div>

        <div class="relative hidden w-1/2 overflow-hidden bg-sidebar lg:flex lg:flex-col lg:items-center lg:justify-center lg:gap-4">
            <div class="absolute inset-0" :style="panelGradient" />
            <AppBrandMark size="lg" :branding="branding" />
            <div class="relative text-center">
                <p class="text-2xl font-semibold text-slate-900 dark:text-white">{{ branding.app_name }}</p>
                <p v-if="branding.tagline" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ branding.tagline }}</p>
            </div>
        </div>

        <div class="flex w-full items-center justify-center px-4 py-10 pb-[max(2.5rem,env(safe-area-inset-bottom))] lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-8 flex flex-col items-center lg:hidden">
                    <AppBrandMark size="lg" :branding="branding" />
                    <p class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ branding.app_name }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-surface p-6 shadow-xl shadow-slate-200/60 dark:border-slate-800 dark:shadow-black/30 sm:p-8">
                    <div class="mb-8 text-center lg:text-left">
                        <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Change your password</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            You signed in with a temporary password. Choose a new password to continue.
                        </p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <UiInput
                            v-model="form.current_password"
                            label="Current (temporary) password"
                            type="password"
                            required
                            :error="form.errors.current_password"
                        />
                        <UiInput
                            v-model="form.password"
                            label="New password"
                            type="password"
                            required
                            :error="form.errors.password"
                        />
                        <UiInput
                            v-model="form.password_confirmation"
                            label="Confirm new password"
                            type="password"
                            required
                            :error="form.errors.password_confirmation"
                        />

                        <UiButton type="submit" class="w-full" variant="primary" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Update password' }}
                        </UiButton>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
