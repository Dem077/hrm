<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

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
    <Head title="Login" />

    <div class="flex min-h-screen items-center justify-center bg-stone-50 px-4">
        <div class="w-full max-w-md rounded-2xl border border-stone-200 bg-white p-8 shadow-sm">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-semibold text-amber-700">HRM</h1>
                <p class="mt-2 text-sm text-stone-500">Sign in to manage attendance devices</p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="email">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2"
                        required
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="password">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none ring-amber-500 focus:ring-2"
                        required
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-stone-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-stone-300 text-amber-600" />
                    Remember me
                </label>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Sign in
                </button>
            </form>
        </div>
    </div>
</template>
