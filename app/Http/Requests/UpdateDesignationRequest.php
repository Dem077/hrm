<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesDesignationPayrollItems;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDesignationRequest extends FormRequest
{
    use ValidatesDesignationPayrollItems;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\Designation $designation */
        $designation = $this->route('designation');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:designations,code,'.$designation->id],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            ...$this->designationPayrollItemRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateDesignationPayrollItems($validator);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'code' => $this->input('code') ?: null,
        ]);
    }
}
