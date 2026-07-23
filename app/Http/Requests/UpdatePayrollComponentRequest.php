<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\PayrollApplicabilityField;
use App\Enums\PayrollApplicabilityOperator;
use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Models\Nationality;
use App\Models\PayrollComponent;
use App\Services\Payroll\PayrollFormulaEvaluator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePayrollComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PayrollComponent $component */
        $component = $this->route('payroll_component');

        if ($component->isSystemMandatory()) {
            throw new HttpResponseException(
                redirect()->back()->with('error', 'System payroll components cannot be edited this way.')
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
        $method = PayrollComponentCalculationMethod::tryFrom((string) $this->input('calculation_method'));
        $isCustom = $method?->isCustomFormula() ?? false;
        $fields = array_map(
            fn (PayrollApplicabilityField $field) => $field->value,
            PayrollApplicabilityField::cases(),
        );
        $operators = array_map(
            fn (PayrollApplicabilityOperator $operator) => $operator->value,
            PayrollApplicabilityOperator::cases(),
        );

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('payroll_components', 'code')->ignore($component->id)],
            'type' => ['required', Rule::enum(PayrollComponentType::class)],
            'calculation_method' => ['required', Rule::enum(PayrollComponentCalculationMethod::class)],
            'calculation_formula' => [$isCustom ? 'required' : 'nullable', 'string', 'max:1000'],
            'is_mandatory' => ['boolean'],
            'applicability_rules' => ['nullable', 'array'],
            'applicability_rules.all' => ['nullable', 'array'],
            'applicability_rules.all.*.field' => ['required_with:applicability_rules.all', 'string', Rule::in($fields)],
            'applicability_rules.all.*.operator' => ['required_with:applicability_rules.all', 'string', Rule::in($operators)],
            'applicability_rules.all.*.value' => ['required_with:applicability_rules.all', 'string', 'max:100'],
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

            if ($method?->usesGlobalRate() && ! $method->isCustomFormula()) {
                $validator->errors()->add(
                    'calculation_method',
                    'Late fine and absent fee methods are reserved for system components.',
                );
            }

            foreach ($this->input('applicability_rules.all', []) as $index => $rule) {
                if (! is_array($rule)) {
                    continue;
                }

                if (($rule['field'] ?? null) === PayrollApplicabilityField::EmploymentType->value
                    && EmploymentType::tryFrom((string) ($rule['value'] ?? '')) === null
                ) {
                    $validator->errors()->add(
                        "applicability_rules.all.{$index}.value",
                        'Select a valid employment type.',
                    );
                }

                if (($rule['field'] ?? null) === PayrollApplicabilityField::Nationality->value
                    && ! Nationality::query()
                        ->where('name', trim((string) ($rule['value'] ?? '')))
                        ->exists()
                ) {
                    $validator->errors()->add(
                        "applicability_rules.all.{$index}.value",
                        'Select a valid nationality.',
                    );
                }
            }

            if ($method?->isCustomFormula()) {
                $formula = trim((string) $this->input('calculation_formula', ''));

                if ($formula === '') {
                    $validator->errors()->add('calculation_formula', 'Enter a calculation formula.');

                    return;
                }

                try {
                    app(PayrollFormulaEvaluator::class)->assertValid($formula);
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($field, $message);
                        }
                    }
                }
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
            $merge['calculation_formula'] = null;
            $merge['applicability_rules'] = null;
        }

        if (! ($merge['is_mandatory'] ?? $this->boolean('is_mandatory'))) {
            $merge['applicability_rules'] = null;
        }

        $method = PayrollComponentCalculationMethod::tryFrom((string) $this->input('calculation_method'));
        if (! $method?->isCustomFormula()) {
            $merge['calculation_formula'] = null;
        } else {
            $merge['calculation_formula'] = trim((string) $this->input('calculation_formula', ''));
        }

        $this->merge($merge);
    }
}
