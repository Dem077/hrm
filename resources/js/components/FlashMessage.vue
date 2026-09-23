<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

type EmployeeUnsetFlash = {
    message: string;
    context: string;
    fields: string[];
    field_labels: string[];
    employees: Array<{
        id: number;
        staff_id: string;
        name: string;
        edit_url: string;
        fields: string[];
        field_labels: string[];
    }>;
};

const page = usePage<{
    flash: {
        success?: string;
        error?: string;
        employee_unset?: EmployeeUnsetFlash | null;
    };
}>();

const unsetPrompt = computed(() => page.props.flash.employee_unset ?? null);
const message = computed(() => unsetPrompt.value?.message || page.props.flash.success || page.props.flash.error);
const tone = computed(() => (unsetPrompt.value || page.props.flash.error ? 'error' : 'success'));
const isSingleEmployee = computed(() => (unsetPrompt.value?.employees.length ?? 0) === 1);

let timeout: ReturnType<typeof setTimeout> | undefined;

watch(message, (value) => {
    if (timeout) {
        clearTimeout(timeout);
    }

    if (!value || unsetPrompt.value) {
        return;
    }

    timeout = setTimeout(() => {
        page.props.flash.success = undefined;
        page.props.flash.error = undefined;
    }, 5000);
});

function dismissUnset() {
    page.props.flash.employee_unset = null;
    page.props.flash.error = undefined;
}
</script>

<template>
    <div
        v-if="message"
        class="mb-6 rounded-2xl border px-4 py-3 text-sm shadow-sm"
        :class="
            tone === 'error'
                ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-800/60 dark:bg-red-950/40 dark:text-red-300'
                : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300'
        "
    >
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    :d="
                        tone === 'error'
                            ? 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'
                            : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
                    "
                />
            </svg>
            <div class="min-w-0 flex-1 space-y-2">
                <p>{{ message }}</p>

                <template v-if="unsetPrompt">
                    <p v-if="isSingleEmployee" class="text-xs opacity-90">
                        Add the missing information for this employee, then try again.
                    </p>
                    <p v-else class="text-xs opacity-90">
                        Update these fields on the listed employees:
                        {{ unsetPrompt.field_labels.join(', ') }}.
                    </p>

                    <ul class="space-y-1.5">
                        <li
                            v-for="employee in unsetPrompt.employees"
                            :key="employee.id"
                            class="flex flex-wrap items-center gap-x-2 gap-y-1"
                        >
                            <Link
                                :href="employee.edit_url"
                                class="font-medium underline decoration-red-300 underline-offset-2 hover:decoration-red-500 dark:decoration-red-700"
                            >
                                {{ employee.name }} ({{ employee.staff_id }})
                            </Link>
                            <span class="text-xs opacity-80">— {{ employee.field_labels.join(', ') }}</span>
                        </li>
                    </ul>

                    <button
                        type="button"
                        class="text-xs font-medium underline underline-offset-2 opacity-80 hover:opacity-100"
                        @click="dismissUnset"
                    >
                        Dismiss
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
