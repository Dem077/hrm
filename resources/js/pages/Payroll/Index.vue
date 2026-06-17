<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';

type PayrollRow = {
    employee_id: number;
    staff_id: string;
    employee_name: string;
    department: string | null;
    designation: string | null;
    days_attended: number;
    hours_worked: number;
    gross: number;
    deductions: number;
    net: number;
    details: Array<{
        component: string;
        method: string;
        rate: number;
        amount: number;
        type: string;
    }>;
};

const props = defineProps<{
    rows: PayrollRow[];
    period: { from: string; to: string; label: string };
    periodOptions: Array<{ offset: number; label: string }>;
    departments: Array<{ id: number; name: string }>;
    filters: { period_offset: number; department_id: number | null };
}>();

const filters = reactive({
    period_offset: props.filters.period_offset ?? 0,
    department_id: props.filters.department_id ?? '',
});

watch(
    () => props.filters,
    (value) => {
        filters.period_offset = value.period_offset ?? 0;
        filters.department_id = value.department_id ?? '';
    },
    { deep: true },
);

const totals = computed(() => {
    return props.rows.reduce(
        (carry, row) => ({
            gross: carry.gross + row.gross,
            deductions: carry.deductions + row.deductions,
            net: carry.net + row.net,
        }),
        { gross: 0, deductions: 0, net: 0 },
    );
});

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    params.set('period_offset', String(filters.period_offset));

    if (filters.department_id) {
        params.set('department_id', String(filters.department_id));
    }

    return `/payroll/export?${params.toString()}`;
});

function applyFilters() {
    router.get(
        '/payroll',
        {
            period_offset: filters.period_offset,
            department_id: filters.department_id || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function formatMoney(value: number): string {
    return value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Payroll" />

    <AppLayout>
        <PageHeader
            title="Payroll"
            description="Process staff payroll for a payroll period and export an Excel sheet with calculated details."
        >
            <template #actions>
                <UiButton :href="exportUrl" external variant="primary">
                    Export Excel
                </UiButton>
            </template>
        </PageHeader>

        <UiCard title="Payroll filters" description="Choose the payroll period and optional department scope.">
            <div class="grid gap-4 md:grid-cols-3">
                <UiSelect v-model="filters.period_offset" label="Payroll period">
                    <option v-for="option in periodOptions" :key="option.offset" :value="option.offset">
                        {{ option.label }}
                    </option>
                </UiSelect>

                <UiSelect v-model="filters.department_id" label="Department">
                    <option value="">All departments</option>
                    <option v-for="department in departments" :key="department.id" :value="department.id">
                        {{ department.name }}
                    </option>
                </UiSelect>

                <div class="flex items-end">
                    <UiButton variant="secondary" class="w-full md:w-auto" @click="applyFilters">
                        Process payroll
                    </UiButton>
                </div>
            </div>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                Selected period: {{ period.label }} ({{ period.from }} to {{ period.to }})
            </p>
        </UiCard>

        <UiCard
            title="Payroll result"
            :description="`${rows.length} staff processed. Gross ${formatMoney(totals.gross)}, deductions ${formatMoney(totals.deductions)}, net ${formatMoney(totals.net)}.`"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <th class="px-3 py-2">Staff</th>
                            <th class="px-3 py-2">Department</th>
                            <th class="px-3 py-2">Designation</th>
                            <th class="px-3 py-2">Days</th>
                            <th class="px-3 py-2">Hours</th>
                            <th class="px-3 py-2">Gross</th>
                            <th class="px-3 py-2">Deductions</th>
                            <th class="px-3 py-2">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="row in rows" :key="row.employee_id">
                            <td class="px-3 py-2">
                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ row.employee_name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.staff_id }}</p>
                            </td>
                            <td class="px-3 py-2">{{ row.department ?? '—' }}</td>
                            <td class="px-3 py-2">{{ row.designation ?? '—' }}</td>
                            <td class="px-3 py-2">{{ row.days_attended }}</td>
                            <td class="px-3 py-2">{{ row.hours_worked }}</td>
                            <td class="px-3 py-2">{{ formatMoney(row.gross) }}</td>
                            <td class="px-3 py-2">{{ formatMoney(row.deductions) }}</td>
                            <td class="px-3 py-2 font-semibold text-brand-700 dark:text-brand-300">
                                {{ formatMoney(row.net) }}
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="8" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">
                                No staff matched the selected filters. Assign designations to employees to include them in payroll.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>
    </AppLayout>
</template>
