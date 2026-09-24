<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Support\EmployeeUnset;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends StoreEmployeeRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (! $this->filled('device_password')) {
            $this->offsetUnset('device_password');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');
        $emailIsUnset = EmployeeUnset::isUnset($this->input('email'));

        return [
            ...$this->employeeRules(),
            ...$this->roleRules(),
            'staff_id' => ['required', 'string', 'max:50', Rule::unique('employees', 'staff_id')->ignore($employee->id)],
            'national_id' => ['required', 'string', 'max:50', Rule::unique('employees', 'national_id')->ignore($employee->id)],
            'email' => array_values(array_filter([
                'required',
                'string',
                'max:255',
                $emailIsUnset ? null : 'email',
                $emailIsUnset ? null : Rule::unique('employees', 'email')->ignore($employee->id),
                $emailIsUnset ? null : Rule::unique('users', 'email')->ignore($employee->user_id),
            ])),
            'login_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee->user_id),
            ],
            'manager_id' => [
                'nullable',
                'exists:employees,id',
                Rule::notIn([$employee->id]),
            ],
        ];
    }
}
