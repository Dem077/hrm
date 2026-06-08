<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends StoreEmployeeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            ...$this->employeeRules(),
            'staff_id' => ['required', 'string', 'max:50', Rule::unique('employees', 'staff_id')->ignore($employee->id)],
            'national_id' => ['required', 'string', 'max:50', Rule::unique('employees', 'national_id')->ignore($employee->id)],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->ignore($employee->id),
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
