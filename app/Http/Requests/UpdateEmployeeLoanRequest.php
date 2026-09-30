<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeLoanRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'monthly_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'loan_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'loan_bank' => ['nullable', 'string', 'max:50', Rule::exists('banks', 'code')->where('is_active', true)],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function loanAttributes(): array
    {
        $data = $this->validated();

        return [
            'employee_id' => (int) $data['employee_id'],
            'name' => $data['name'],
            'monthly_amount' => $data['monthly_amount'],
            'loan_months' => filled($data['loan_months'] ?? null) ? (int) $data['loan_months'] : null,
            'loan_bank' => filled($data['loan_bank'] ?? null) ? (string) $data['loan_bank'] : null,
            'start_date' => filled($data['start_date'] ?? null) ? $data['start_date'] : null,
            'notes' => filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
