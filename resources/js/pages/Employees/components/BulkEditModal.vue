<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

import GradeSelect from '@/components/ui/GradeSelect.vue';
import type { GradeOption } from '@/components/ui/GradeSelect.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';
import UiSearchableSelect from '@/components/ui/UiSearchableSelect.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import type { DevicePrivilegeOption, DutyTypeOption, EnumOption, SelectOption } from '@/types/hrm';

type ApplyField =
    | 'is_active'
    | 'works_saturday'
    | 'duty_type'
    | 'employment_type'
    | 'grade_id'
    | 'manager_id'
    | 'bank_name'
    | 'work_location'
    | 'nationality'
    | 'religion'
    | 'qualification'
    | 'device_privilege'
    | 'zkt_location_group_ids';

const props = defineProps<{
    open: boolean;
    employeeIds: number[];
    selectedCount: number;
    grades: GradeOption[];
    managers: SelectOption[];
    employmentTypes: EnumOption[];
    dutyTypes: DutyTypeOption[];
    banks: EnumOption[];
    nationalities: EnumOption[];
    devicePrivileges: DevicePrivilegeOption[];
    locationGroups: Array<{ id: number; name: string; code: string | null }>;
}>();

const emit = defineEmits<{
    close: [];
    success: [];
}>();

const apply = reactive<Record<ApplyField, boolean>>({
    is_active: false,
    works_saturday: false,
    duty_type: false,
    employment_type: false,
    grade_id: false,
    manager_id: false,
    bank_name: false,
    work_location: false,
    nationality: false,
    religion: false,
    qualification: false,
    device_privilege: false,
    zkt_location_group_ids: false,
});

const values = reactive({
    is_active: '1',
    works_saturday: '0',
    duty_type: 'normal',
    employment_type: '',
    grade_id: '' as string | number,
    manager_id: '' as string | number,
    bank_name: '',
    work_location: '',
    nationality: '',
    religion: '',
    qualification: '',
    device_privilege: 'employee',
    zkt_location_group_ids: [] as number[],
});

const form = useForm({
    employee_ids: [] as number[],
});

const managerOptions = computed(() =>
    props.managers.map((manager) => ({
        value: manager.id,
        label: manager.label ?? manager.name ?? String(manager.id),
    })),
);

const appliedCount = computed(() => Object.values(apply).filter(Boolean).length);

const canSubmit = computed(
    () => props.employeeIds.length > 0 && appliedCount.value > 0 && !form.processing,
);

function resetForm(): void {
    apply.is_active = false;
    apply.works_saturday = false;
    apply.duty_type = false;
    apply.employment_type = false;
    apply.grade_id = false;
    apply.manager_id = false;
    apply.bank_name = false;
    apply.work_location = false;
    apply.nationality = false;
    apply.religion = false;
    apply.qualification = false;
    apply.device_privilege = false;
    apply.zkt_location_group_ids = false;

    values.is_active = '1';
    values.works_saturday = '0';
    values.duty_type = 'normal';
    values.employment_type = '';
    values.grade_id = '';
    values.manager_id = '';
    values.bank_name = '';
    values.work_location = '';
    values.nationality = '';
    values.religion = '';
    values.qualification = '';
    values.device_privilege = 'employee';
    values.zkt_location_group_ids = [];

    form.reset();
    form.clearErrors();
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
            form.employee_ids = [...props.employeeIds];
        }
    },
);

function toggleLocationGroup(groupId: number): void {
    const index = values.zkt_location_group_ids.indexOf(groupId);

    if (index >= 0) {
        values.zkt_location_group_ids.splice(index, 1);
        return;
    }

    values.zkt_location_group_ids.push(groupId);
}

function buildPayload(): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        employee_ids: [...props.employeeIds],
    };

    if (apply.is_active) {
        payload.is_active = values.is_active === '1';
    }

    if (apply.works_saturday) {
        payload.works_saturday = values.works_saturday === '1';
    }

    if (apply.duty_type) {
        payload.duty_type = values.duty_type;
    }

    if (apply.employment_type) {
        payload.employment_type = values.employment_type;
    }

    if (apply.grade_id) {
        payload.grade_id = values.grade_id || '';
    }

    if (apply.manager_id) {
        payload.manager_id = values.manager_id || '';
    }

    if (apply.bank_name) {
        payload.bank_name = values.bank_name;
    }

    if (apply.work_location) {
        payload.work_location = values.work_location;
    }

    if (apply.nationality) {
        payload.nationality = values.nationality;
    }

    if (apply.religion) {
        payload.religion = values.religion;
    }

    if (apply.qualification) {
        payload.qualification = values.qualification;
    }

    if (apply.device_privilege) {
        payload.device_privilege = values.device_privilege;
    }

    if (apply.zkt_location_group_ids) {
        payload.zkt_location_group_ids = [...values.zkt_location_group_ids];
    }

    return payload;
}

function close(): void {
    emit('close');
}

function submit(): void {
    form.employee_ids = [...props.employeeIds];
    form
        .transform(() => buildPayload())
        .post('/employees/bulk-update', {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
                close();
            },
            onFinish: () => {
                form.transform((data) => data);
            },
        });
}
</script>

<template>
    <UiModal
        :open="open"
        title="Bulk edit employees"
        :description="`Apply the same values to ${selectedCount} selected employee${selectedCount === 1 ? '' : 's'}. Only checked fields are changed.`"
        max-width="xl"
        @close="close"
    >
        <div class="space-y-4">
            <p v-if="form.errors.employee_ids" class="text-sm text-red-600 dark:text-red-400">
                {{ form.errors.employee_ids }}
            </p>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.is_active" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiSelect v-model="values.is_active" label="Status" :disabled="!apply.is_active" :error="form.errors.is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </UiSelect>
                </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.works_saturday" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiSelect
                        v-model="values.works_saturday"
                        label="Works Saturday"
                        :disabled="!apply.works_saturday"
                        :error="form.errors.works_saturday"
                    >
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </UiSelect>
                </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.duty_type" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiSelect
                        v-model="values.duty_type"
                        label="Duty type"
                        hint="Shift duty also clears custom duty times."
                        :disabled="!apply.duty_type"
                        :error="form.errors.duty_type"
                    >
                        <option v-for="option in dutyTypes" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </UiSelect>
                </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.employment_type" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiSelect
                        v-model="values.employment_type"
                        label="Employment type"
                        :disabled="!apply.employment_type"
                        :error="form.errors.employment_type"
                    >
                        <option value="">Clear</option>
                        <option v-for="option in employmentTypes" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </UiSelect>
                </div>
            </label>

            <div class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.grade_id" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <GradeSelect
                        v-model="values.grade_id"
                        label="Designation / grade"
                        empty-label="Unassigned"
                        :options="grades"
                        :error="form.errors.grade_id"
                    />
                </div>
            </div>

            <div class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.manager_id" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiSearchableSelect
                        v-model="values.manager_id"
                        label="Manager"
                        empty-label="No manager"
                        placeholder="Search manager…"
                        :options="managerOptions"
                        :error="form.errors.manager_id"
                    />
                </div>
            </div>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.bank_name" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiSelect v-model="values.bank_name" label="Bank" :disabled="!apply.bank_name" :error="form.errors.bank_name">
                        <option value="">Clear</option>
                        <option v-for="bank in banks" :key="bank.value" :value="bank.value">{{ bank.label }}</option>
                    </UiSelect>
                </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.work_location" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiInput
                        v-model="values.work_location"
                        label="Work location"
                        :error="form.errors.work_location"
                    />
                </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.nationality" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiSelect
                        v-model="values.nationality"
                        label="Nationality"
                        :disabled="!apply.nationality"
                        :error="form.errors.nationality"
                    >
                        <option value="">Clear</option>
                        <option v-for="option in nationalities" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </UiSelect>
                </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.religion" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiInput v-model="values.religion" label="Religion" :error="form.errors.religion" />
                </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.qualification" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiInput
                        v-model="values.qualification"
                        label="Qualification"
                        :error="form.errors.qualification"
                    />
                </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input v-model="apply.device_privilege" type="checkbox" class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                <div class="min-w-0 flex-1">
                    <UiSelect
                        v-model="values.device_privilege"
                        label="Device privilege"
                        :disabled="!apply.device_privilege"
                        :error="form.errors.device_privilege"
                    >
                        <option v-for="option in devicePrivileges" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </UiSelect>
                </div>
            </label>

            <div class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input
                    v-model="apply.zkt_location_group_ids"
                    type="checkbox"
                    class="mt-2.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                />
                <div class="min-w-0 flex-1">
                    <p class="mb-1.5 text-sm font-medium text-slate-700 dark:text-slate-300">Location groups</p>
                    <p class="mb-2 text-xs text-slate-500">Replaces current groups for every selected employee.</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="group in locationGroups"
                            :key="group.id"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                :disabled="!apply.zkt_location_group_ids"
                                :checked="values.zkt_location_group_ids.includes(group.id)"
                                @change="toggleLocationGroup(group.id)"
                            />
                            <span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ group.name }}</span>
                                <span v-if="group.code" class="block text-xs text-slate-500">{{ group.code }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors.zkt_location_group_ids" class="mt-2 text-xs text-red-600 dark:text-red-400">
                        {{ form.errors.zkt_location_group_ids }}
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <UiButton variant="ghost" :disabled="form.processing" @click="close">Cancel</UiButton>
            <UiButton variant="primary" :disabled="!canSubmit" @click="submit">
                {{ form.processing ? 'Saving…' : `Update ${selectedCount}` }}
            </UiButton>
        </template>
    </UiModal>
</template>
