<?php

namespace App\Http\Requests;

use App\Enums\DutyType;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\BloodGroup;
use App\Enums\MaritalStatus;
use App\Enums\ZktDevicePrivilege;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->employeeRules(),
            ...$this->roleRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $dutyType = $this->input('duty_type', DutyType::Normal->value);
        $isShiftDuty = $dutyType === DutyType::Shift->value;
        $usesCustomDutyTimes = ! $isShiftDuty && $this->boolean('uses_custom_duty_times', false);

        $merge = [
            'is_active' => $this->boolean('is_active', true),
            'works_saturday' => $this->boolean('works_saturday', false),
            'duty_type' => $dutyType,
            'uses_custom_duty_times' => $usesCustomDutyTimes,
            'department_id' => $this->input('department_id') ?: null,
            'designation_id' => $this->input('designation_id') ?: null,
            'device_privilege' => $this->input('device_privilege', ZktDevicePrivilege::Employee->value),
            'device_card_number' => $this->normalizeDeviceCardNumber($this->input('device_card_number')),
            'device_password' => $this->normalizeDevicePassword($this->input('device_password')),
            'remove_profile_photo' => $this->boolean('remove_profile_photo', false),
            'manager_id' => $this->input('manager_id') ?: null,
            'zkt_location_group_ids' => collect($this->input('zkt_location_group_ids', []))
                ->filter(fn ($id) => filled($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
            'current_address' => $this->input('current_address') ?: null,
            'permanent_address' => $this->input('permanent_address') ?: null,
            'ext_no' => $this->input('ext_no') ?: null,
            'personal_email' => $this->input('personal_email') ?: null,
            'office_email' => $this->input('office_email') ?: null,
            'emergency_contact_name' => $this->input('emergency_contact_name') ?: null,
            'emergency_contact_number' => $this->input('emergency_contact_number') ?: null,
            'marital_status' => $this->input('marital_status') ?: null,
            'blood_group' => $this->input('blood_group') ?: null,
            'date_of_birth' => $this->input('date_of_birth') ?: null,
            'nationality' => $this->input('nationality') ?: null,
            'religion' => $this->input('religion') ?: null,
            'work_location' => $this->input('work_location') ?: null,
            'qualification' => $this->input('qualification') ?: null,
            'employment_type' => $this->input('employment_type') ?: null,
        ];

        if ($usesCustomDutyTimes) {
            foreach (['custom_duty_start_time', 'custom_duty_end_time'] as $field) {
                if ($this->filled($field)) {
                    $merge[$field] = $this->normalizeTimeForValidation($this->input($field)).':00';
                }
            }

            foreach (['custom_saturday_duty_start_time', 'custom_saturday_duty_end_time'] as $field) {
                $merge[$field] = $this->filled($field)
                    ? $this->normalizeTimeForValidation($this->input($field)).':00'
                    : null;
            }

            $merge['custom_grace_minutes'] = $this->filled('custom_grace_minutes')
                ? (int) $this->input('custom_grace_minutes')
                : null;
            $merge['custom_saturday_grace_minutes'] = $this->filled('custom_saturday_grace_minutes')
                ? (int) $this->input('custom_saturday_grace_minutes')
                : null;
        } else {
            $merge['custom_duty_start_time'] = null;
            $merge['custom_duty_end_time'] = null;
            $merge['custom_grace_minutes'] = null;
            $merge['custom_saturday_duty_start_time'] = null;
            $merge['custom_saturday_duty_end_time'] = null;
            $merge['custom_saturday_grace_minutes'] = null;
        }

        $this->merge($merge);
    }

    protected function normalizeTimeForValidation(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return strlen($time) === 5 ? $time : substr($time, 0, 5);
    }

    protected function normalizeDeviceCardNumber(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    protected function normalizeDevicePassword(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return substr((string) $value, 0, 8);
    }

    /**
     * @return array<string, mixed>
     */
    protected function employeeRules(): array
    {
        return [
            'staff_id' => ['required', 'string', 'max:50', 'unique:employees,staff_id'],
            'name' => ['required', 'string', 'max:255'],
            'national_id' => ['required', 'string', 'max:50', 'unique:employees,national_id'],
            'email' => ['required', 'email', 'max:255', 'unique:employees,email', 'unique:users,email'],
            'mobile_number' => ['nullable', 'string', 'max:30'],
            'joined_date' => ['required', 'date'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'device_privilege' => ['nullable', Rule::enum(ZktDevicePrivilege::class)],
            'device_card_number' => ['nullable', 'string', 'max:10', 'regex:/^\d+$/'],
            'device_password' => ['nullable', 'string', 'max:8', 'regex:/^\d+$/'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
            'remove_profile_photo' => ['nullable', 'boolean'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['boolean'],
            'works_saturday' => ['boolean'],
            'duty_type' => ['required', Rule::enum(DutyType::class)],
            'uses_custom_duty_times' => ['boolean'],
            'custom_duty_start_time' => ['nullable', 'required_if:uses_custom_duty_times,true', 'prohibited_if:duty_type,shift', 'date_format:H:i:s'],
            'custom_duty_end_time' => ['nullable', 'required_if:uses_custom_duty_times,true', 'prohibited_if:duty_type,shift', 'date_format:H:i:s', 'after:custom_duty_start_time'],
            'custom_grace_minutes' => ['nullable', 'prohibited_if:duty_type,shift', 'integer', 'min:0', 'max:180'],
            'custom_saturday_duty_start_time' => ['nullable', 'prohibited_if:duty_type,shift', 'date_format:H:i:s'],
            'custom_saturday_duty_end_time' => ['nullable', 'prohibited_if:duty_type,shift', 'date_format:H:i:s', 'after:custom_saturday_duty_start_time'],
            'custom_saturday_grace_minutes' => ['nullable', 'prohibited_if:duty_type,shift', 'integer', 'min:0', 'max:180'],
            'zkt_location_group_ids' => ['nullable', 'array'],
            'zkt_location_group_ids.*' => ['integer', 'exists:zkt_location_groups,id'],
            'current_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'ext_no' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'office_email' => ['nullable', 'email', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'blood_group' => ['nullable', Rule::enum(BloodGroup::class)],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_no' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function roleRules(): array
    {
        if (! $this->user()?->can('users.assign-roles')) {
            return [];
        }

        return [
            'role_names' => ['nullable', 'array'],
            'role_names.*' => [
                'string',
                Rule::exists('roles', 'name'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (
                        $value === PermissionRegistry::superAdminRole()
                        && ! $this->user()?->hasRole(PermissionRegistry::superAdminRole())
                    ) {
                        $fail('You cannot assign the Super Admin role.');
                    }
                },
            ],
        ];
    }
}
