<?php

namespace App\Http\Requests;

use App\Enums\DutyType;
use App\Enums\EmploymentType;
use App\Enums\ZktDevicePrivilege;
use App\Support\EmployeeUnset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateEmployeesRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const PATCH_FIELDS = [
        'grade_id',
        'manager_id',
        'is_active',
        'works_saturday',
        'duty_type',
        'employment_type',
        'bank_name',
        'work_location',
        'nationality',
        'religion',
        'qualification',
        'device_privilege',
        'uses_custom_duty_times',
        'custom_duty_start_time',
        'custom_duty_end_time',
        'custom_grace_minutes',
        'custom_saturday_duty_start_time',
        'custom_saturday_duty_end_time',
        'custom_saturday_grace_minutes',
        'zkt_location_group_ids',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'employee_ids' => collect($this->input('employee_ids', []))
                ->filter(fn ($id) => filled($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ];

        if ($this->has('grade_id')) {
            $merge['grade_id'] = $this->input('grade_id') ?: null;
        }

        if ($this->has('manager_id')) {
            $merge['manager_id'] = $this->input('manager_id') ?: null;
        }

        if ($this->has('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }

        if ($this->has('works_saturday')) {
            $merge['works_saturday'] = $this->boolean('works_saturday');
        }

        if ($this->has('employment_type')) {
            $merge['employment_type'] = $this->input('employment_type') ?: null;
        }

        if ($this->has('bank_name')) {
            $raw = trim((string) ($this->input('bank_name') ?? ''));
            $merge['bank_name'] = ($raw === '' || EmployeeUnset::isUnset($raw))
                ? EmployeeUnset::VALUE
                : mb_strtoupper($raw);
        }

        if ($this->has('work_location')) {
            $raw = trim((string) ($this->input('work_location') ?? ''));
            $merge['work_location'] = ($raw === '' || EmployeeUnset::isUnset($raw))
                ? EmployeeUnset::VALUE
                : $raw;
        }

        if ($this->has('nationality')) {
            $raw = trim((string) ($this->input('nationality') ?? ''));
            $merge['nationality'] = ($raw === '' || EmployeeUnset::isUnset($raw))
                ? EmployeeUnset::VALUE
                : $raw;
        }

        if ($this->has('religion')) {
            $merge['religion'] = $this->input('religion') ?: null;
        }

        if ($this->has('qualification')) {
            $merge['qualification'] = $this->input('qualification') ?: null;
        }

        if ($this->has('zkt_location_group_ids')) {
            $merge['zkt_location_group_ids'] = collect($this->input('zkt_location_group_ids', []))
                ->filter(fn ($id) => filled($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $dutyType = $this->has('duty_type')
            ? ($this->input('duty_type') ?: DutyType::Normal->value)
            : null;

        if ($dutyType !== null) {
            $merge['duty_type'] = $dutyType;
        }

        $isShiftDuty = $dutyType === DutyType::Shift->value;

        if ($isShiftDuty) {
            $merge['uses_custom_duty_times'] = false;
            $merge['custom_duty_start_time'] = null;
            $merge['custom_duty_end_time'] = null;
            $merge['custom_grace_minutes'] = null;
            $merge['custom_saturday_duty_start_time'] = null;
            $merge['custom_saturday_duty_end_time'] = null;
            $merge['custom_saturday_grace_minutes'] = null;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'grade_id' => ['sometimes', 'nullable', 'exists:structure_grades,id'],
            'manager_id' => ['sometimes', 'nullable', 'exists:employees,id'],
            'is_active' => ['sometimes', 'boolean'],
            'works_saturday' => ['sometimes', 'boolean'],
            'duty_type' => ['sometimes', Rule::enum(DutyType::class)],
            'employment_type' => ['sometimes', 'nullable', Rule::enum(EmploymentType::class)],
            'bank_name' => array_values(array_filter([
                'sometimes',
                'nullable',
                'string',
                'max:50',
                EmployeeUnset::isUnset($this->input('bank_name'))
                    ? null
                    : Rule::exists('banks', 'code')->where('is_active', true),
            ])),
            'work_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nationality' => array_values(array_filter([
                'sometimes',
                'nullable',
                'string',
                'max:100',
                EmployeeUnset::isUnset($this->input('nationality'))
                    ? null
                    : Rule::exists('nationalities', 'name'),
            ])),
            'religion' => ['sometimes', 'nullable', 'string', 'max:100'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'device_privilege' => ['sometimes', Rule::enum(ZktDevicePrivilege::class)],
            'zkt_location_group_ids' => ['sometimes', 'array'],
            'zkt_location_group_ids.*' => ['integer', 'exists:zkt_location_groups,id'],
            'uses_custom_duty_times' => ['sometimes', 'boolean'],
            'custom_duty_start_time' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'custom_duty_end_time' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'custom_grace_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:180'],
            'custom_saturday_duty_start_time' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'custom_saturday_duty_end_time' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'custom_saturday_grace_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:180'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasPatch = collect(self::PATCH_FIELDS)->contains(
                fn (string $field) => array_key_exists($field, $this->all()),
            );

            if (! $hasPatch) {
                $validator->errors()->add('employee_ids', 'Choose at least one field to update.');
            }
        });
    }

    /**
     * Column values to write onto each selected employee.
     *
     * @return array<string, mixed>
     */
    public function employeePatch(): array
    {
        return $this->safe()->except('employee_ids', 'zkt_location_group_ids');
    }
}
