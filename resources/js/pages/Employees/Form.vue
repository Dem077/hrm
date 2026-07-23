<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import ProfilePhotoUpload from '@/components/ui/ProfilePhotoUpload.vue';
import GradeSelect from '@/components/ui/GradeSelect.vue';
import type { GradeOption } from '@/components/ui/GradeSelect.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiManagedSelect from '@/components/ui/UiManagedSelect.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import UiTextarea from '@/components/ui/UiTextarea.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { DevicePrivilegeOption, DutyTypeOption, Employee, EnumOption, GenderOption, SelectOption } from '@/types/hrm';

type EmployeeFormTab = 'profile' | 'details' | 'organization' | 'attendance' | 'machine' | 'access';

const props = defineProps<{
    employee: Employee;
    grades: GradeOption[];
    locationGroups: Array<{ id: number; name: string; code: string | null }>;
    devicePrivileges: DevicePrivilegeOption[];
    managers: SelectOption[];
    genders: GenderOption[];
    maritalStatuses: EnumOption[];
    bloodGroups: EnumOption[];
    employmentTypes: EnumOption[];
    dutyTypes: DutyTypeOption[];
    banks: EnumOption[];
    nationalities: EnumOption[];
    roles: Array<{ id: number; name: string }>;
    canAssignRoles: boolean;
}>();

const isEditing = computed(() => props.employee.id !== null);
const activeTab = ref<EmployeeFormTab>('profile');

const tabFields: Record<EmployeeFormTab, string[]> = {
    profile: [
        'staff_id',
        'name',
        'national_id',
        'gender',
        'email',
        'mobile_number',
        'joined_date',
        'is_active',
        'password',
        'profile_photo',
        'remove_profile_photo',
    ],
    details: [
        'current_address',
        'permanent_address',
        'ext_no',
        'personal_email',
        'office_email',
        'emergency_contact_name',
        'emergency_contact_number',
        'marital_status',
        'blood_group',
        'date_of_birth',
        'nationality',
        'religion',
        'work_location',
        'qualification',
        'employment_type',
        'bank_name',
        'account_name',
        'account_no',
    ],
    organization: ['grade_id', 'manager_id'],
    attendance: [
        'duty_type',
        'works_saturday',
        'uses_custom_duty_times',
        'custom_duty_start_time',
        'custom_duty_end_time',
        'custom_grace_minutes',
        'custom_saturday_duty_start_time',
        'custom_saturday_duty_end_time',
        'custom_saturday_grace_minutes',
    ],
    machine: ['device_privilege', 'device_card_number', 'device_password', 'zkt_location_group_ids'],
    access: ['role_names'],
};

const tabs = computed(() => {
    const items: Array<{ id: EmployeeFormTab; label: string }> = [
        { id: 'profile', label: 'Profile' },
        { id: 'details', label: 'Profile details' },
        { id: 'organization', label: 'Organization' },
        { id: 'attendance', label: 'Duty Policy' },
        { id: 'machine', label: 'Machine access' },
    ];

    if (props.canAssignRoles) {
        items.push({ id: 'access', label: 'Access roles' });
    }

    return items;
});

const form = useForm({
    staff_id: props.employee.staff_id,
    name: props.employee.name,
    profile_photo: null as File | null,
    remove_profile_photo: false,
    national_id: props.employee.national_id,
    email: props.employee.email ?? '',
    mobile_number: props.employee.mobile_number ?? '',
    joined_date: props.employee.joined_date ?? '',
    gender: props.employee.gender,
    grade_id: props.employee.grade_id ?? '',
    device_privilege: props.employee.device_privilege ?? 'employee',
    device_card_number: props.employee.device_card_number ?? '',
    device_password: '',
    manager_id: props.employee.manager_id ?? '',
    password: '',
    is_active: props.employee.is_active,
    works_saturday: props.employee.works_saturday ?? false,
    duty_type: props.employee.duty_type ?? 'normal',
    uses_custom_duty_times: props.employee.uses_custom_duty_times ?? false,
    custom_duty_start_time: props.employee.custom_duty_start_time ?? '09:00',
    custom_duty_end_time: props.employee.custom_duty_end_time ?? '18:00',
    custom_grace_minutes: props.employee.custom_grace_minutes ?? 15,
    custom_saturday_duty_start_time: props.employee.custom_saturday_duty_start_time ?? '09:00',
    custom_saturday_duty_end_time: props.employee.custom_saturday_duty_end_time ?? '14:00',
    custom_saturday_grace_minutes: props.employee.custom_saturday_grace_minutes ?? 15,
    role_names: [...(props.employee.role_names ?? [])],
    zkt_location_group_ids: [...(props.employee.zkt_location_group_ids ?? [])],
    current_address: props.employee.current_address ?? '',
    permanent_address: props.employee.permanent_address ?? '',
    ext_no: props.employee.ext_no ?? '',
    personal_email: props.employee.personal_email ?? '',
    office_email: props.employee.office_email ?? '',
    emergency_contact_name: props.employee.emergency_contact_name ?? '',
    emergency_contact_number: props.employee.emergency_contact_number ?? '',
    marital_status: props.employee.marital_status ?? '',
    blood_group: props.employee.blood_group ?? '',
    date_of_birth: props.employee.date_of_birth ?? '',
    nationality: props.employee.nationality ?? '',
    religion: props.employee.religion ?? '',
    work_location: props.employee.work_location ?? '',
    qualification: props.employee.qualification ?? '',
    employment_type: props.employee.employment_type ?? '',
    bank_name: props.employee.bank_name ?? '',
    account_name: props.employee.account_name ?? '',
    account_no: props.employee.account_no ?? '',
});

const selectedGrade = computed(() => {
    const gradeId = Number(form.grade_id);
    if (!gradeId) {
        return null;
    }

    return props.grades.find((grade) => grade.id === gradeId) ?? null;
});

type LoginEmailSource = 'office' | 'personal';

function detectLoginEmailSource(): LoginEmailSource {
    const email = (props.employee.email ?? '').trim().toLowerCase();
    const personal = (props.employee.personal_email ?? '').trim().toLowerCase();
    const office = (props.employee.office_email ?? '').trim().toLowerCase();

    if (email && personal && email === personal) {
        return 'personal';
    }

    if (email && office && email === office) {
        return 'office';
    }

    return 'office';
}

const loginEmailSource = ref<LoginEmailSource>(detectLoginEmailSource());

const selectedLoginEmail = computed(() => {
    const value = loginEmailSource.value === 'personal' ? form.personal_email : form.office_email;

    return (value ?? '').trim();
});

const lengthOfServiceLabel = computed(() => {
    if (!form.joined_date) {
        return '—';
    }

    const start = new Date(`${form.joined_date}T00:00:00`);
    const end = new Date();
    end.setHours(0, 0, 0, 0);

    if (Number.isNaN(start.getTime()) || start > end) {
        return '0 days';
    }

    let years = end.getFullYear() - start.getFullYear();
    let months = end.getMonth() - start.getMonth();
    let days = end.getDate() - start.getDate();

    if (days < 0) {
        months -= 1;
        const previousMonth = new Date(end.getFullYear(), end.getMonth(), 0);
        days += previousMonth.getDate();
    }

    if (months < 0) {
        years -= 1;
        months += 12;
    }

    const parts: string[] = [];

    if (years > 0) {
        parts.push(`${years} ${years === 1 ? 'year' : 'years'}`);
    }

    if (months > 0) {
        parts.push(`${months} ${months === 1 ? 'month' : 'months'}`);
    }

    if (parts.length === 0 && days > 0) {
        parts.push(`${days} ${days === 1 ? 'day' : 'days'}`);
    }

    return parts.length ? parts.join(', ') : 'Less than 1 day';
});

const photoPreview = ref<string | null>(props.employee.profile_photo_url ?? null);
const pendingPhotoName = ref<string | null>(null);

watch(
    () => form.profile_photo,
    (file) => {
        if (!file) {
            pendingPhotoName.value = null;
            photoPreview.value = form.remove_profile_photo ? null : (props.employee.profile_photo_url ?? null);

            return;
        }

        pendingPhotoName.value = file.name;
        photoPreview.value = URL.createObjectURL(file);
    },
);

function onPhotoSelect(file: File) {
    form.profile_photo = file;
    form.remove_profile_photo = false;
}

function removePhoto() {
    form.profile_photo = null;
    form.remove_profile_photo = true;
    pendingPhotoName.value = null;
    photoPreview.value = null;
}

function tabHasErrors(tabId: EmployeeFormTab): boolean {
    return tabFields[tabId].some((field) => Boolean(form.errors[field as keyof typeof form.errors]));
}

function focusTab(tabId: EmployeeFormTab) {
    activeTab.value = tabId;
}

function toggleRole(roleName: string) {
    const index = form.role_names.indexOf(roleName);

    if (index >= 0) {
        form.role_names.splice(index, 1);
        return;
    }

    form.role_names.push(roleName);
}

function toggleLocationGroup(groupId: number) {
    const index = form.zkt_location_group_ids.indexOf(groupId);

    if (index >= 0) {
        form.zkt_location_group_ids.splice(index, 1);
        return;
    }

    form.zkt_location_group_ids.push(groupId);
}

function submit() {
    form.email = selectedLoginEmail.value;

    const options = { forceFormData: true };

    if (isEditing.value) {
        form.put(`/employees/${props.employee.id}`, options);
        return;
    }

    form.post('/employees', options);
}

watch(
    () => form.errors,
    (errors) => {
        if (Object.keys(errors).length === 0) {
            return;
        }

        const tabWithError = tabs.value.find((tab) => tabHasErrors(tab.id));

        if (tabWithError) {
            activeTab.value = tabWithError.id;
        }
    },
    { deep: true },
);
</script>

<template>
    <Head :title="isEditing ? 'Edit Employee' : 'Add Employee'" />

    <AppLayout>
        <PageHeader
            :title="isEditing ? 'Edit employee' : 'Add employee'"
            description="Creates the employee record and login account together from one form."
        >
            <template #actions>
                <UiButton href="/employees" variant="ghost">Cancel</UiButton>
            </template>
        </PageHeader>

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <div class="overflow-x-auto border-b border-slate-200 dark:border-slate-800">
                <div class="flex min-w-max gap-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="relative whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition"
                        :class="
                            activeTab === tab.id
                                ? 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-300'
                                : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200'
                        "
                        @click="focusTab(tab.id)"
                    >
                        {{ tab.label }}
                        <span
                            v-if="tabHasErrors(tab.id)"
                            class="ml-2 inline-flex h-2 w-2 rounded-full bg-red-500"
                            aria-label="Has validation errors"
                        />
                    </button>
                </div>
            </div>

            <div v-show="activeTab === 'profile'" class="space-y-6">
                <UiCard title="Profile photo" description="Used on the employee list, profile page, and anywhere this person appears in the app.">
                    <ProfilePhotoUpload
                        :preview-url="photoPreview"
                        :name="form.name"
                        :error="form.errors.profile_photo"
                        :can-remove="Boolean(photoPreview || props.employee.profile_photo_url) && !form.remove_profile_photo"
                        :marked-for-removal="form.remove_profile_photo"
                        :pending-file-name="pendingPhotoName"
                        @select="onPhotoSelect"
                        @remove="removePhoto"
                    />
                </UiCard>

                <UiCard title="Personal details">
                    <div class="grid gap-5 md:grid-cols-2">
                        <UiInput v-model="form.staff_id" label="Staff ID" required :error="form.errors.staff_id" />
                        <UiInput v-model="form.name" label="Full name" required :error="form.errors.name" />
                        <UiInput v-model="form.national_id" label="National ID" required :error="form.errors.national_id" />
                        <UiSelect v-model="form.gender" label="Gender" :error="form.errors.gender">
                            <option v-for="option in genders" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </UiSelect>
                        <UiSelect v-model="loginEmailSource" label="Login email" required :error="form.errors.email">
                            <option value="office">Office email</option>
                            <option value="personal">Personal email</option>
                        </UiSelect>
                        <UiInput
                            :model-value="selectedLoginEmail || '—'"
                            label="Login email address"
                            hint="Uses the selected email from Profile details. Office email is selected by default."
                            readonly
                        />
                        <UiInput v-model="form.mobile_number" label="Mobile number" :error="form.errors.mobile_number" />
                        <UiInput v-model="form.joined_date" label="Joined date" type="date" required :error="form.errors.joined_date" />
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 bg-white text-brand-600 dark:border-slate-600 dark:bg-surface dark:text-brand-500" />
                            Employee is active
                        </label>
                    </div>
                </UiCard>

                <UiCard
                    :title="isEditing ? 'Login password' : 'Login account'"
                    :description="
                        isEditing
                            ? 'Leave blank to keep the current password.'
                            : 'Leave blank to auto-generate a temporary password shown after save.'
                    "
                >
                    <UiInput
                        v-model="form.password"
                        label="Password"
                        type="password"
                        :hint="isEditing ? 'Optional — only fill in to reset password' : 'Optional — min 8 characters'"
                        :error="form.errors.password"
                    />
                </UiCard>
            </div>

            <div v-show="activeTab === 'details'" class="space-y-6">
                <UiCard title="Contact & address" description="Optional contact details and addresses.">
                    <div class="grid gap-5 md:grid-cols-2">
                        <UiTextarea v-model="form.current_address" label="Current address" :error="form.errors.current_address" />
                        <UiTextarea v-model="form.permanent_address" label="Permanent address" :error="form.errors.permanent_address" />
                        <UiInput v-model="form.ext_no" label="Ext No" :error="form.errors.ext_no" />
                        <UiInput v-model="form.personal_email" label="Personal email" type="email" :error="form.errors.personal_email" />
                        <UiInput v-model="form.office_email" label="Office email" type="email" :error="form.errors.office_email" />
                        <UiInput v-model="form.emergency_contact_name" label="Emergency contact name" :error="form.errors.emergency_contact_name" />
                        <UiInput v-model="form.emergency_contact_number" label="Emergency contact number" :error="form.errors.emergency_contact_number" />
                    </div>
                </UiCard>

                <UiCard title="Personal information" description="Optional personal and employment profile fields.">
                    <div class="grid gap-5 md:grid-cols-2">
                        <UiSelect v-model="form.marital_status" label="Marital status" :error="form.errors.marital_status">
                            <option value="">Not specified</option>
                            <option v-for="option in maritalStatuses" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </UiSelect>
                        <UiSelect v-model="form.blood_group" label="Blood group" :error="form.errors.blood_group">
                            <option value="">Not specified</option>
                            <option v-for="option in bloodGroups" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </UiSelect>
                        <UiInput v-model="form.date_of_birth" label="Date of birth" type="date" :error="form.errors.date_of_birth" />
                        <UiManagedSelect
                            v-model="form.nationality"
                            label="Nationality"
                            :options="nationalities"
                            placeholder="Search nationality..."
                            empty-label="Not specified"
                            record-label="Nationality"
                            create-modal-title="Create nationality"
                            manage-modal-title="Manage nationalities"
                            store-url="/nationalities"
                            :destroy-url="(value) => `/nationalities/${encodeURIComponent(value)}`"
                            :error="form.errors.nationality"
                        />
                        <UiInput v-model="form.religion" label="Religion" :error="form.errors.religion" />
                        <UiInput v-model="form.work_location" label="Work location" :error="form.errors.work_location" />
                        <UiInput v-model="form.qualification" label="Qualification" :error="form.errors.qualification" />
                        <UiSelect v-model="form.employment_type" label="Employment type" :error="form.errors.employment_type">
                            <option value="">Not specified</option>
                            <option v-for="option in employmentTypes" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </UiSelect>
                        <UiInput
                            :model-value="lengthOfServiceLabel"
                            label="Length of service"
                            hint="Auto-generated from joined date on the Profile tab."
                            readonly
                        />
                    </div>
                </UiCard>

                <UiCard title="Banking details" description="Required for payroll and reimbursements.">
                    <div class="grid gap-5 md:grid-cols-3">
                        <UiSelect v-model="form.bank_name" label="Bank name" required :error="form.errors.bank_name">
                            <option value="">Select bank</option>
                            <option
                                v-if="form.bank_name && !banks.some((bank) => bank.value === form.bank_name)"
                                :value="form.bank_name"
                            >
                                {{ form.bank_name }} (current)
                            </option>
                            <option v-for="bank in banks" :key="bank.value" :value="bank.value">
                                {{ bank.label }}
                            </option>
                        </UiSelect>
                        <UiInput v-model="form.account_name" label="Account name" required :error="form.errors.account_name" />
                        <UiInput v-model="form.account_no" label="Account no" required :error="form.errors.account_no" />
                    </div>
                </UiCard>
            </div>

            <div v-show="activeTab === 'organization'" class="space-y-6">
                <UiCard title="Organization & approvals" description="Assign a grade from Company Structure and one direct manager for approvals.">
                    <div class="grid gap-5 md:grid-cols-2">
                        <GradeSelect
                            v-model="form.grade_id"
                            :options="grades"
                            :error="form.errors.grade_id"
                        />
                        <UiSelect v-model="form.manager_id" label="Direct manager" :error="form.errors.manager_id">
                            <option value="">No manager</option>
                            <option v-for="manager in managers" :key="manager.id" :value="manager.id">
                                {{ manager.label }}
                            </option>
                        </UiSelect>
                    </div>
                    <p
                        v-if="selectedGrade"
                        class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600 dark:bg-slate-900/50 dark:text-slate-300"
                    >
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ selectedGrade.label }}</span>
                        <span v-if="selectedGrade.context_label" class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                            {{ selectedGrade.context_label }}
                        </span>
                    </p>
                </UiCard>
            </div>

            <div v-show="activeTab === 'attendance'" class="space-y-6">
                <UiCard title="Duty type" description="Normal duty follows global settings. Shift duty uses the duty roster for daily timings.">
                    <UiSelect v-model="form.duty_type" label="Duty type" :error="form.errors.duty_type">
                        <option v-for="option in dutyTypes" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </UiSelect>
                    <p v-if="form.duty_type === 'shift'" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        Assign daily duty timings on the <a href="/duty-rosters" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Duty Roster</a> page.
                    </p>
                    <label
                        v-if="form.duty_type === 'normal'"
                        class="mt-5 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300"
                    >
                        <input v-model="form.works_saturday" type="checkbox" class="rounded border-slate-300 bg-white text-brand-600 dark:border-slate-600 dark:bg-surface dark:text-brand-500" />
                        Works on Saturday (uses Saturday duty policy from global settings)
                    </label>
                </UiCard>

                <UiCard
                    v-if="form.duty_type === 'normal'"
                    title="Custom duty times"
                    description="Bypass the global duty policy for this employee and use their own duty start, end, and grace minutes on the attendance sheet."
                >
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300">
                        <input
                            v-model="form.uses_custom_duty_times"
                            type="checkbox"
                            class="rounded border-slate-300 bg-white text-brand-600 dark:border-slate-600 dark:bg-surface dark:text-brand-500"
                        />
                        Use custom duty times instead of global policy
                    </label>

                    <div v-if="form.uses_custom_duty_times" class="mt-5 grid gap-5 md:grid-cols-2">
                        <UiInput
                            v-model="form.custom_duty_start_time"
                            label="Duty start"
                            type="time"
                            required
                            :error="form.errors.custom_duty_start_time"
                        />
                        <UiInput
                            v-model="form.custom_duty_end_time"
                            label="Duty end"
                            type="time"
                            required
                            :error="form.errors.custom_duty_end_time"
                        />
                        <UiInput
                            v-model="form.custom_grace_minutes"
                            label="Grace minutes"
                            type="number"
                            min="0"
                            max="180"
                            :error="form.errors.custom_grace_minutes"
                        />

                        <template v-if="form.works_saturday">
                            <UiInput
                                v-model="form.custom_saturday_duty_start_time"
                                label="Saturday start"
                                type="time"
                                hint="Optional — falls back to weekday duty start when empty."
                                :error="form.errors.custom_saturday_duty_start_time"
                            />
                            <UiInput
                                v-model="form.custom_saturday_duty_end_time"
                                label="Saturday end"
                                type="time"
                                hint="Optional — falls back to weekday duty end when empty."
                                :error="form.errors.custom_saturday_duty_end_time"
                            />
                            <UiInput
                                v-model="form.custom_saturday_grace_minutes"
                                label="Saturday grace minutes"
                                type="number"
                                min="0"
                                max="180"
                                hint="Optional — falls back to weekday grace when empty."
                                :error="form.errors.custom_saturday_grace_minutes"
                            />
                        </template>
                    </div>
                </UiCard>
            </div>

            <div v-show="activeTab === 'machine'" class="space-y-6">
                <UiCard title="Machine access" description="Assign location groups for direct machine access.">
                    <UiSelect v-model="form.device_privilege" label="Device privilege" :error="form.errors.device_privilege">
                        <option v-for="option in devicePrivileges" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </UiSelect>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Controls the privilege level pushed to biometric machines when this employee is synced. Defaults to Employee.
                    </p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <UiInput
                            v-model="form.device_card_number"
                            label="Card number"
                            inputmode="numeric"
                            hint="Optional RFID/card number synced to machines (digits only, up to 10)."
                            :error="form.errors.device_card_number"
                        />
                        <UiInput
                            v-model="form.device_password"
                            label="Device password"
                            type="password"
                            inputmode="numeric"
                            autocomplete="new-password"
                            :hint="
                                isEditing
                                    ? props.employee.has_device_password
                                        ? 'Leave blank to keep the current device password.'
                                        : 'Optional — up to 8 digits for machine menu access.'
                                    : 'Optional — up to 8 digits for machine menu access.'
                            "
                            :error="form.errors.device_password"
                        />
                    </div>

                    <div class="mt-5">
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Location groups</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="group in locationGroups"
                                :key="group.id"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-surface-elevated"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-600 dark:border-slate-600 dark:bg-surface"
                                    :checked="form.zkt_location_group_ids.includes(group.id)"
                                    @change="toggleLocationGroup(group.id)"
                                />
                                <span>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ group.name }}</span>
                                    <span v-if="group.code" class="block text-xs text-slate-500">{{ group.code }}</span>
                                </span>
                            </label>
                        </div>
                        <p v-if="locationGroups.length === 0" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                            Create machine location groups under Configurations first.
                        </p>
                        <p v-if="form.errors.zkt_location_group_ids" class="mt-3 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.zkt_location_group_ids }}
                        </p>
                    </div>
                </UiCard>
            </div>

            <div v-if="canAssignRoles" v-show="activeTab === 'access'" class="space-y-6">
                <UiCard
                    title="Access roles"
                    description="Choose which roles this login account should have. Sidebar and actions follow these permissions."
                >
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="role in roles"
                            :key="role.id"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm transition hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-surface-elevated"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-brand-600 dark:border-slate-600 dark:bg-surface"
                                :checked="form.role_names.includes(role.name)"
                                @change="toggleRole(role.name)"
                            />
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ role.name }}</span>
                        </label>
                    </div>
                    <p v-if="form.errors.role_names" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ form.errors.role_names }}</p>
                </UiCard>
            </div>

            <div class="flex items-center justify-between gap-4 border-t border-slate-200 pt-6 dark:border-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Changes apply across all tabs when you save.
                </p>
                <UiButton type="submit" variant="primary" :disabled="form.processing">
                    {{ isEditing ? 'Update employee' : 'Create employee' }}
                </UiButton>
            </div>
        </form>
    </AppLayout>
</template>
