<?php

namespace App\Http\Requests;

use App\Enums\DutyType;
use App\Http\Requests\Concerns\ValidatesDutyRosterEmployeeAccess;
use App\Models\DutyRoster;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDutyRosterRequest extends FormRequest
{
    use ValidatesDutyRosterEmployeeAccess;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var DutyRoster $dutyRoster */
        $dutyRoster = $this->route('duty_roster');

        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'duty_date' => [
                'required',
                'date',
                Rule::unique('duty_rosters', 'duty_date')
                    ->where('employee_id', $this->integer('employee_id'))
                    ->ignore($dutyRoster->id),
            ],
            'duty_start_time' => ['required', 'date_format:H:i:s'],
            'duty_end_time' => ['required', 'date_format:H:i:s', 'after:duty_start_time'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employee = Employee::query()->find($this->integer('employee_id'));

            if ($employee && $employee->duty_type !== DutyType::Shift) {
                $validator->errors()->add('employee_id', 'Duty roster entries can only be assigned to shift duty employees.');
            }

            if ($employee) {
                $this->validateDutyRosterEmployeeAccess($validator, $employee->id);
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'grace_minutes' => $this->filled('grace_minutes') ? (int) $this->input('grace_minutes') : 15,
            'notes' => $this->input('notes') ?: null,
        ];

        foreach (['duty_start_time', 'duty_end_time'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = $this->normalizeTimeForValidation($this->input($field)).':00';
            }
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
}
