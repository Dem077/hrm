<?php

namespace App\Http\Requests;

use App\Enums\DutyType;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\BloodGroup;
use App\Enums\MaritalStatus;
use App\Enums\ZktDevicePrivilege;
use App\Support\EmployeeUnset;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
        $staffId = trim((string) $this->input('staff_id', ''));

        $nationalId = $this->normalizeUnsettable($this->input('national_id'));
        if ($nationalId === null && $staffId !== '') {
            $nationalId = EmployeeUnset::VALUE.'-'.$staffId;
        }

        $email = $this->normalizeUnsettable($this->input('email'));
        $loginEmail = $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL)
            ? mb_strtolower($email)
            : ($staffId !== '' ? 'unset.'.Str::slug($staffId, '').'@import.local' : null);

        $merge = [
            'is_active' => $this->boolean('is_active', true),
            'works_saturday' => $this->boolean('works_saturday', false),
            'duty_type' => $dutyType,
            'uses_custom_duty_times' => $usesCustomDutyTimes,
            'grade_id' => $this->input('grade_id') ?: null,
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
            'national_id' => $nationalId ?? EmployeeUnset::VALUE,
            'email' => $email ?? EmployeeUnset::VALUE,
            'login_email' => $loginEmail,
            'mobile_number' => $this->normalizeUnsettable($this->input('mobile_number')) ?? EmployeeUnset::VALUE,
            'joined_date' => $this->normalizeUnsettable($this->input('joined_date')),
            'current_address' => $this->input('current_address') ?: null,
            'permanent_address' => $this->input('permanent_address') ?: null,
            'ext_no' => $this->input('ext_no') ?: null,
            'personal_email' => $this->normalizeOptionalEmail($this->input('personal_email')),
            'office_email' => $this->normalizeOptionalEmail($this->input('office_email')),
            'emergency_contact_name' => $this->input('emergency_contact_name') ?: null,
            'emergency_contact_number' => $this->normalizeUnsettable($this->input('emergency_contact_number')) ?? EmployeeUnset::VALUE,
            'marital_status' => $this->input('marital_status') ?: null,
            'blood_group' => $this->input('blood_group') ?: null,
            'date_of_birth' => $this->input('date_of_birth') ?: null,
            'nationality' => $this->normalizeUnsettable($this->input('nationality')) ?? EmployeeUnset::VALUE,
            'religion' => $this->input('religion') ?: null,
            'work_location' => $this->normalizeUnsettable($this->input('work_location')) ?? EmployeeUnset::VALUE,
            'qualification' => $this->input('qualification') ?: null,
            'employment_type' => $this->input('employment_type') ?: null,
            'bank_name' => $this->normalizeBankCode($this->input('bank_name')),
            'account_name' => $this->normalizeUnsettable($this->input('account_name')) ?? EmployeeUnset::VALUE,
            'account_no' => $this->normalizeUnsettable($this->input('account_no')) ?? EmployeeUnset::VALUE,
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

    protected function normalizeUnsettable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '' || strcasecmp($raw, 'NULL') === 0 || EmployeeUnset::isUnset($raw)) {
            return null;
        }

        return $raw;
    }

    protected function normalizeOptionalEmail(mixed $value): ?string
    {
        $normalized = $this->normalizeUnsettable($value);

        return $normalized === null ? EmployeeUnset::VALUE : $normalized;
    }

    protected function normalizeBankCode(mixed $value): string
    {
        $normalized = $this->normalizeUnsettable($value);

        return $normalized === null ? EmployeeUnset::VALUE : mb_strtoupper($normalized);
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
        $emailIsUnset = EmployeeUnset::isUnset($this->input('email'));
        $nationalityIsUnset = EmployeeUnset::isUnset($this->input('nationality'));
        $bankIsUnset = EmployeeUnset::isUnset($this->input('bank_name'));
        $personalEmailIsUnset = EmployeeUnset::isUnset($this->input('personal_email'));
        $officeEmailIsUnset = EmployeeUnset::isUnset($this->input('office_email'));

        return [
            'staff_id' => ['required', 'string', 'max:50', 'unique:employees,staff_id'],
            'name' => ['required', 'string', 'max:255'],
            'national_id' => ['required', 'string', 'max:50', 'unique:employees,national_id'],
            'email' => array_values(array_filter([
                'required',
                'string',
                'max:255',
                $emailIsUnset ? null : 'email',
                $emailIsUnset ? null : 'unique:employees,email',
                $emailIsUnset ? null : 'unique:users,email',
            ])),
            'login_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile_number' => ['nullable', 'string', 'max:30'],
            'joined_date' => ['nullable', 'date'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'grade_id' => ['nullable', 'exists:structure_grades,id'],
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
            'personal_email' => array_values(array_filter([
                'nullable',
                'string',
                'max:255',
                $personalEmailIsUnset ? null : 'email',
            ])),
            'office_email' => array_values(array_filter([
                'nullable',
                'string',
                'max:255',
                $officeEmailIsUnset ? null : 'email',
            ])),
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'blood_group' => ['nullable', Rule::enum(BloodGroup::class)],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'nationality' => array_values(array_filter([
                'nullable',
                'string',
                'max:100',
                $nationalityIsUnset ? null : Rule::exists('nationalities', 'name'),
            ])),
            'religion' => ['nullable', 'string', 'max:100'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'bank_name' => array_values(array_filter([
                'nullable',
                'string',
                'max:50',
                $bankIsUnset ? null : Rule::exists('banks', 'code')->where('is_active', true),
            ])),
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_no' => ['nullable', 'string', 'max:50'],
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
