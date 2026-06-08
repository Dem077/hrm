<?php

namespace App\Http\Requests;

use App\Enums\Gender;
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
        return $this->employeeRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'works_saturday' => $this->boolean('works_saturday', false),
            'department_id' => $this->input('department_id') ?: null,
            'manager_id' => $this->input('manager_id') ?: null,
        ]);
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
        ];
    }
}
