<?php

namespace App\Http\Requests;

use App\Enums\PayrollComponentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollComponentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:payroll_components,code'],
            'type' => ['required', Rule::enum(PayrollComponentType::class)],
            'is_mandatory' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_mandatory' => $this->boolean('is_mandatory'),
            'is_active' => $this->boolean('is_active', true),
            'code' => $this->input('code') ?: null,
        ]);
    }
}
