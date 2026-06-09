<?php

namespace App\Http\Requests;

use App\Enums\PayrollComponentType;
use App\Models\PayrollComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdatePayrollComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PayrollComponent $component */
        $component = $this->route('payroll_component');

        if ($component->isSystemMandatory()) {
            throw new HttpResponseException(
                redirect()->back()->with('error', 'Basic Salary cannot be edited.')
            );
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PayrollComponent $component */
        $component = $this->route('payroll_component');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('payroll_components', 'code')->ignore($component->id)],
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
