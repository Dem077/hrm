<?php

namespace App\Http\Requests;

use App\Enums\DutyType;
use App\Enums\Gender;
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
            'manager_id' => $this->input('manager_id') ?: null,
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
