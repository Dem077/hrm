<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import EmptyState from '@/components/ui/EmptyState.vue';
import EmployeeAvatar from '@/components/ui/EmployeeAvatar.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiActionMenu from '@/components/ui/UiActionMenu.vue';
import UiActionMenuItem from '@/components/ui/UiActionMenuItem.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import type { GradeOption } from '@/components/ui/GradeSelect.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import BulkEditModal from '@/pages/Employees/components/BulkEditModal.vue';
import ImportPreviewModal from '@/pages/Employees/components/ImportPreviewModal.vue';
import type { EmployeeImportPreview } from '@/pages/Employees/components/ImportPreviewModal.vue';
import type { Employee, EnumOption, SelectOption, DevicePrivilegeOption, DutyTypeOption } from '@/types/hrm';
import type { Paginated } from '@/types/attendance';

const props = defineProps<{
    employees: Paginated<Employee>;
    filters: {
        search?: string | null;
    };
    importPreview?: EmployeeImportPreview | null;
    importFileName?: string | null;
    grades: GradeOption[];
    managers: SelectOption[];
    employmentTypes: EnumOption[];
    dutyTypes: DutyTypeOption[];
    banks: EnumOption[];
    nationalities: EnumOption[];
    devicePrivileges: DevicePrivilegeOption[];
    locationGroups: Array<{ id: number; name: string; code: string | null }>;
}>();

const { can } = usePermissions();
const canCreate = can('employees.create');
const canUpdate = can('employees.update');
const canImportCsv = canCreate || canUpdate;
const searchText = ref(props.filters.search ?? '');
const fileInput = ref<HTMLInputElement | null>(null);
const importForm = useForm<{ file: File | null }>({
    file: null,
});
const selectedIds = ref<number[]>([]);
const bulkEditOpen = ref(false);

const previewOpen = computed(() => Boolean(props.importPreview));

watch(
    () => props.filters.search,
    (value) => {
        searchText.value = value ?? '';
    },
);

const pageEmployees = computed(() => props.employees.data);
const pageIds = computed(() =>
    pageEmployees.value
        .map((employee) => employee.id)
        .filter((id): id is number => typeof id === 'number'),
);

const allPageSelected = computed(() => {
    if (pageIds.value.length === 0) {
        return false;
    }

    return pageIds.value.every((id) => selectedIds.value.includes(id));
});

const selectedOutsidePageCount = computed(() => {
    const visible = new Set(pageIds.value);

    return selectedIds.value.filter((id) => !visible.has(id)).length;
});

const hasEmployees = computed(() => props.employees.total > 0);
const hasMatches = computed(() => pageEmployees.value.length > 0);

function employeeId(employee: Employee): number | null {
    return typeof employee.id === 'number' ? employee.id : null;
}

function isSelected(id: number | null): boolean {
    return id !== null && selectedIds.value.includes(id);
}

function toggleEmployee(id: number | null): void {
    if (id === null) {
        return;
    }

    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((selected) => selected !== id);
        return;
    }

    selectedIds.value = [...selectedIds.value, id];
}

function toggleSelectAllPage(): void {
    if (allPageSelected.value) {
        const page = new Set(pageIds.value);
        selectedIds.value = selectedIds.value.filter((id) => !page.has(id));
        return;
    }

    const next = new Set(selectedIds.value);
    pageIds.value.forEach((id) => next.add(id));
    selectedIds.value = Array.from(next);
}

function clearSelection(): void {
    selectedIds.value = [];
}

function applySearch(): void {
    router.get(
        '/employees',
        { search: searchText.value.trim() || undefined },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function clearSearch(): void {
    searchText.value = '';
    applySearch();
}

function downloadSampleCsv(): void {
    globalThis.location.assign('/employees/sample-csv');
}

function exportEmployeesCsv(): void {
    globalThis.location.assign('/employees/export-csv');
}

function openImportPicker(): void {
    fileInput.value?.click();
}

function onImportFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (!file) {
        return;
    }

    importForm.file = file;
    importForm.post('/employees/import/preview', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importForm.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}
</script>

<template>
    <Head title="Employees" />

    <AppLayout>
        <PageHeader
            title="Employees"
            description="Manage staff records, grade assignments, and reporting lines for approvals."
        >
            <template #actions>
                <UiActionMenu label="CSV" variant="secondary" size="md">
                    <UiActionMenuItem @click="exportEmployeesCsv">Export current employees</UiActionMenuItem>
                    <UiActionMenuItem v-if="canImportCsv" @click="downloadSampleCsv">Download blank template</UiActionMenuItem>
                    <UiActionMenuItem
                        v-if="canImportCsv"
                        :disabled="importForm.processing"
                        @click="openImportPicker"
                    >
                        {{ importForm.processing ? 'Preparing…' : 'Import CSV' }}
                    </UiActionMenuItem>
                </UiActionMenu>
                <UiButton v-if="canCreate" href="/employees/create" variant="primary">Add employee</UiButton>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".csv,text/csv"
                    class="hidden"
                    @change="onImportFileChange"
                />
            </template>
        </PageHeader>

        <p v-if="importForm.errors.file" class="mb-4 text-sm text-red-600">{{ importForm.errors.file }}</p>

        <div class="mb-4 flex flex-col gap-4">
            <form class="flex max-w-xl flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="applySearch">
                <div class="min-w-0 flex-1">
                    <UiInput
                        v-model="searchText"
                        label="Search"
                        placeholder="Name, staff ID, NID, email, or manager"
                    />
                </div>
                <UiButton type="submit" variant="primary">Search</UiButton>
                <UiButton
                    v-if="filters.search"
                    type="button"
                    variant="ghost"
                    @click="clearSearch"
                >
                    Clear
                </UiButton>
            </form>
            <p v-if="hasEmployees" class="text-xs text-slate-500">
                Showing {{ employees.from }}–{{ employees.to }} of {{ employees.total }}
            </p>

            <div
                v-if="canUpdate && hasMatches"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60"
            >
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <label class="inline-flex items-center gap-2">
                        <input
                            type="checkbox"
                            class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            :checked="allPageSelected"
                            :disabled="pageIds.length === 0"
                            @change="toggleSelectAllPage"
                        />
                        <span>Select page ({{ pageIds.length }})</span>
                    </label>
                    <span v-if="selectedIds.length" class="text-slate-500">
                        {{ selectedIds.length }} selected
                        <template v-if="selectedOutsidePageCount">
                            · {{ selectedOutsidePageCount }} on other pages
                        </template>
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <UiButton v-if="selectedIds.length" size="sm" variant="ghost" @click="clearSelection">
                        Clear selection
                    </UiButton>
                    <UiButton
                        size="sm"
                        variant="primary"
                        :disabled="selectedIds.length === 0"
                        @click="bulkEditOpen = true"
                    >
                        Edit selected
                    </UiButton>
                </div>
            </div>
        </div>

        <EmptyState
            v-if="!hasEmployees && !filters.search"
            title="No employees yet"
            description="Add your first employee to start building departments and approval hierarchies."
        >
            <template #icon>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4" />
                </svg>
            </template>
            <template v-if="canCreate" #action>
                <UiButton href="/employees/create" variant="primary">Add employee</UiButton>
            </template>
        </EmptyState>

        <EmptyState
            v-else-if="!hasMatches"
            title="No matching employees"
            description="Try a different name, staff ID, or email."
        >
            <template #icon>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"
                    />
                </svg>
            </template>
            <template #action>
                <UiButton variant="ghost" @click="clearSearch">Clear search</UiButton>
            </template>
        </EmptyState>

        <template v-else>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="employee in pageEmployees"
                    :key="employee.id ?? employee.staff_id"
                    class="rounded-xl border bg-surface p-4 shadow-sm transition hover:border-brand-500/30 dark:hover:border-brand-500/20"
                    :class="
                        isSelected(employeeId(employee))
                            ? 'border-brand-500/60 dark:border-brand-500/40'
                            : 'border-slate-200 dark:border-slate-800'
                    "
                >
                    <div class="flex items-start gap-3">
                        <input
                            v-if="canUpdate && employeeId(employee) !== null"
                            type="checkbox"
                            class="mt-1.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                            :checked="isSelected(employeeId(employee))"
                            @change="toggleEmployee(employeeId(employee))"
                        />
                        <EmployeeAvatar :photo-url="employee.profile_photo_url" :name="employee.name" size="sm" />
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="`/employees/${employee.id}`"
                                class="block truncate font-semibold text-slate-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                            >
                                {{ employee.name }}
                            </Link>
                            <p class="text-xs text-slate-500">{{ employee.staff_id }}</p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                employee.is_active
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                            "
                        >
                            {{ employee.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <dl class="mt-3 space-y-1.5 text-xs">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Org / Grade</dt>
                            <dd class="truncate text-slate-700 dark:text-slate-300">
                                {{ employee.grade?.label ?? employee.department?.name ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Manager</dt>
                            <dd class="truncate text-slate-700 dark:text-slate-300">{{ employee.manager?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Joined</dt>
                            <dd class="text-slate-700 dark:text-slate-300">{{ formatDate(employee.joined_date) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <UiButton size="sm" :href="`/employees/${employee.id}`" variant="ghost">View</UiButton>
                        <UiButton v-if="can('employees.update')" size="sm" :href="`/employees/${employee.id}/edit`" variant="secondary">Edit</UiButton>
                    </div>
                </article>
            </div>

            <div v-if="employees.links.length > 3" class="mt-5 flex flex-wrap gap-2">
                <Link
                    v-for="link in employees.links"
                    :key="`${link.label}-${link.url}`"
                    :href="link.url ?? '#'"
                    class="rounded-lg border px-3 py-1.5 text-sm transition"
                    :class="[
                        link.active
                            ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-600/40 dark:bg-brand-600/15 dark:text-brand-400'
                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400 dark:hover:bg-surface-muted dark:hover:text-slate-200',
                        !link.url ? 'pointer-events-none opacity-50' : '',
                    ]"
                    preserve-scroll
                    preserve-state
                    v-html="link.label"
                />
            </div>
        </template>

        <ImportPreviewModal
            :open="previewOpen"
            :preview="props.importPreview ?? null"
            :file-name="props.importFileName"
            @close="() => {}"
        />

        <BulkEditModal
            :open="bulkEditOpen"
            :employee-ids="selectedIds"
            :selected-count="selectedIds.length"
            :grades="grades"
            :managers="managers"
            :employment-types="employmentTypes"
            :duty-types="dutyTypes"
            :banks="banks"
            :nationalities="nationalities"
            :device-privileges="devicePrivileges"
            :location-groups="locationGroups"
            @close="bulkEditOpen = false"
            @success="clearSelection"
        />
    </AppLayout>
</template>
