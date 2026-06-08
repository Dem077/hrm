<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Employee, GenderOption, SelectOption } from '@/types/hrm';

const props = defineProps<{
    employee: Employee;
    departments: SelectOption[];
    managers: SelectOption[];
    genders: GenderOption[];
    roles: Array<{ id: number; name: string }>;
    canAssignRoles: boolean;
}>();

const isEditing = computed(() => props.employee.id !== null);

const form = useForm({
    staff_id: props.employee.staff_id,
    name: props.employee.name,
    national_id: props.employee.national_id,
    email: props.employee.email ?? '',
    mobile_number: props.employee.mobile_number ?? '',
    joined_date: props.employee.joined_date ?? '',
    gender: props.employee.gender,
    department_id: props.employee.department_id ?? '',
    manager_id: props.employee.manager_id ?? '',
    password: '',
    is_active: props.employee.is_active,
    works_saturday: props.employee.works_saturday ?? false,
    role_names: [...(props.employee.role_names ?? [])],
});

function toggleRole(roleName: string) {
    const index = form.role_names.indexOf(roleName);

    if (index >= 0) {
        form.role_names.splice(index, 1);
        return;
    }

    form.role_names.push(roleName);
}

function submit() {
    if (isEditing.value) {
        form.put(`/employees/${props.employee.id}`);
        return;
    }

    form.post('/employees');
}
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

        <form class="space-y-6" @submit.prevent="submit">
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
                    <UiInput v-model="form.email" label="Login email" type="email" required :error="form.errors.email" />
                    <UiInput v-model="form.mobile_number" label="Mobile number" :error="form.errors.mobile_number" />
                    <UiInput v-model="form.joined_date" label="Joined date" type="date" required :error="form.errors.joined_date" />
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 bg-white text-brand-600 dark:border-slate-600 dark:bg-surface dark:text-brand-500" />
                        Employee is active
                    </label>
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-300 md:col-span-2">
                        <input v-model="form.works_saturday" type="checkbox" class="rounded border-slate-300 bg-white text-brand-600 dark:border-slate-600 dark:bg-surface dark:text-brand-500" />
                        Works on Saturday (uses Saturday duty policy from global settings)
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

            <UiCard title="Organization & approvals" description="Department and one direct manager for approvals.">
                <div class="grid gap-5 md:grid-cols-2">
                    <UiSelect v-model="form.department_id" label="Department" :error="form.errors.department_id">
                        <option value="">Unassigned</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </UiSelect>
                    <UiSelect v-model="form.manager_id" label="Direct manager" :error="form.errors.manager_id">
                        <option value="">No manager</option>
                        <option v-for="manager in managers" :key="manager.id" :value="manager.id">
                            {{ manager.label }}
                        </option>
                    </UiSelect>
                </div>
            </UiCard>

            <UiCard
                v-if="canAssignRoles"
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

            <div class="flex justify-end">
                <UiButton type="submit" variant="primary" :disabled="form.processing">
                    {{ isEditing ? 'Update employee' : 'Create employee' }}
                </UiButton>
            </div>
        </form>
    </AppLayout>
</template>
