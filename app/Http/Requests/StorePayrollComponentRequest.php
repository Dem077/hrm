<?php

namespace App\Http\Requests;

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'calculation_method' => ['required', Rule::enum(PayrollComponentCalculationMethod::class)],
            'is_mandatory' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') === PayrollComponentType::Loan->value && $this->boolean('is_mandatory')) {
                $validator->errors()->add('is_mandatory', 'Loan components cannot be mandatory for all designations.');
            }

            $method = PayrollComponentCalculationMethod::tryFrom((string) $this->input('calculation_method'));

            if ($method?->usesGlobalRate()) {
                $validator->errors()->add(
                    'calculation_method',
                    'Late fine and absent fee methods are reserved for system components.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'is_mandatory' => $this->boolean('is_mandatory'),
            'is_active' => $this->boolean('is_active', true),
            'code' => $this->input('code') ?: null,
        ];

        if ($this->input('type') === PayrollComponentType::Loan->value) {
            $merge['calculation_method'] = PayrollComponentCalculationMethod::Fixed->value;
            $merge['is_mandatory'] = false;
        }

        $this->merge($merge);
    }
}
