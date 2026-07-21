<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import AppBrandMark from '@/components/AppBrandMark.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import { applyAppBranding } from '@/composables/useAppBranding';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppBranding } from '@/types/branding';

const props = defineProps<{
    settings: AppBranding;
    defaults: AppBranding & { app_name: string; tagline: string };
}>();

const { can } = usePermissions();

const form = useForm({
    app_name: props.settings.app_name,
    tagline: props.settings.tagline ?? '',
    logo: null as File | null,
    remove_logo: false,
    brand_color_400: props.settings.brand_color_400,
    brand_color_500: props.settings.brand_color_500,
    brand_color_600: props.settings.brand_color_600,
    brand_color_700: props.settings.brand_color_700,
});

const logoPreview = ref<string | null>(props.settings.logo_url);

watch(
    () => form.logo,
    (file) => {
        if (!file) {
            logoPreview.value = form.remove_logo ? null : props.settings.logo_url;

            return;
        }

        logoPreview.value = URL.createObjectURL(file);
    },
);

const previewBranding = computed<AppBranding>(() => ({
    app_name: form.app_name,
    tagline: form.tagline || null,
    logo_url: logoPreview.value,
    leave_carry_forward_enabled: props.settings.leave_carry_forward_enabled,
    brand_color_400: form.brand_color_400,
    brand_color_500: form.brand_color_500,
    brand_color_600: form.brand_color_600,
    brand_color_700: form.brand_color_700,
}));

watch(
    previewBranding,
    (branding) => {
        applyAppBranding(branding);
    },
    { deep: true, immediate: true },
);

function onLogoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = file;
    form.remove_logo = false;
}

function removeLogo() {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
}

function resetColors() {
    form.brand_color_400 = props.defaults.brand_color_400;
    form.brand_color_500 = props.defaults.brand_color_500;
    form.brand_color_600 = props.defaults.brand_color_600;
    form.brand_color_700 = props.defaults.brand_color_700;
}

function submit() {
    form.post('/app-settings', {
        preserveScroll: true,
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="App Settings" />

    <AppLayout>
        <PageHeader
            title="App settings"
            description="Customize the application logo, name, and brand colors used across the interface."
        />

        <form class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" @submit.prevent="submit">
            <div class="grid gap-6">
                <UiCard title="Application identity" description="Shown in the sidebar, login screen, and browser title.">
                    <div class="grid gap-4 md:grid-cols-2">
                        <UiInput v-model="form.app_name" label="App name" required :error="form.errors.app_name" />
                        <UiInput v-model="form.tagline" label="Tagline" placeholder="Attendance" :error="form.errors.tagline" />
                    </div>
                </UiCard>

                <UiCard title="Logo" description="Upload a square or horizontal logo. PNG, JPG, SVG, or WebP up to 2 MB.">
                    <div class="flex flex-wrap items-start gap-6">
                        <AppBrandMark size="lg" :branding="previewBranding" />

                        <div class="grid flex-1 gap-3">
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp"
                                class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-500 dark:text-slate-400"
                                :disabled="!can('app-settings.update')"
                                @change="onLogoChange"
                            />
                            <p v-if="form.errors.logo" class="text-sm text-red-600">{{ form.errors.logo }}</p>
                            <UiButton
                                v-if="can('app-settings.update') && (logoPreview || settings.logo_url)"
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="removeLogo"
                            >
                                Remove logo
                            </UiButton>
                        </div>
                    </div>
                </UiCard>

                <UiCard title="Brand colors" description="These colors are applied to buttons, links, and highlights across the app.">
                    <div class="mb-4 flex justify-end">
                        <UiButton type="button" size="sm" variant="ghost" @click="resetColors">Reset to defaults</UiButton>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="shade in ['400', '500', '600', '700']" :key="shade">
                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Brand {{ shade }}</label>
                            <div class="flex items-center gap-3">
                                <input
                                    v-model="form[`brand_color_${shade}` as 'brand_color_400']"
                                    type="color"
                                    class="h-10 w-14 shrink-0 cursor-pointer rounded-lg border border-slate-200 dark:border-slate-700"
                                />
                                <input
                                    v-model="form[`brand_color_${shade}` as 'brand_color_400']"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm uppercase text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                                />
                            </div>
                            <p v-if="form.errors[`brand_color_${shade}`]" class="mt-1 text-xs text-red-600">
                                {{ form.errors[`brand_color_${shade}`] }}
                            </p>
                        </div>
                    </div>
                </UiCard>

                <div v-if="can('app-settings.update')" class="flex justify-end">
                    <UiButton type="submit" :disabled="form.processing">Save settings</UiButton>
                </div>
            </div>

            <UiCard title="Preview" class="h-fit lg:sticky lg:top-6">
                <div class="grid gap-4">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <AppBrandMark :branding="previewBranding" />
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-white">{{ previewBranding.app_name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ previewBranding.tagline || 'Tagline' }}</p>
                        </div>
                    </div>
                    <UiButton type="button">Primary button</UiButton>
                    <UiButton type="button" variant="ghost">Ghost button</UiButton>
                    <div class="flex gap-2">
                        <span class="rounded-full bg-brand-600/15 px-3 py-1 text-xs font-medium text-brand-700 dark:text-brand-400">Badge</span>
                        <span class="text-sm font-medium text-brand-600 dark:text-brand-400">Link color</span>
                    </div>
                </div>
            </UiCard>
        </form>
    </AppLayout>
</template>
