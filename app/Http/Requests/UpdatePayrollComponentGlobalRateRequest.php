<?php

namespace App\Http\Requests;

use App\Enums\PayrollComponentCalculationMethod;
use App\Models\PayrollComponent;
use App\Services\Payroll\PayrollFormulaEvaluator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePayrollComponentGlobalRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PayrollComponent $component */
        $component = $this->route('payroll_component');

        if (! $component->isSystemMandatory() || $component->allowedCalculationMethods() === []) {
            throw new HttpResponseException(
                redirect()->back()->with('error', 'Only system company-rate components can be updated this way.')
            );
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $method = PayrollComponentCalculationMethod::tryFrom((string) $this->input('calculation_method'));

        if (($method?->isCustomFormula() || $this->rateSetPerGrade($method)) && ! $this->filled('global_rate')) {
            $this->merge(['global_rate' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PayrollComponent $component */
        $component = $this->route('payroll_component');

        $allowed = array_map(
            fn (PayrollComponentCalculationMethod $method) => $method->value,
            $component->allowedCalculationMethods(),
        );

        $method = PayrollComponentCalculationMethod::tryFrom((string) $this->input('calculation_method'));
        $isCustom = $method?->isCustomFormula() ?? false;
        $ratePerGrade = $this->rateSetPerGrade($method);

        return [
            'calculation_method' => ['required', 'string', Rule::in($allowed)],
            'global_rate' => [$isCustom || $ratePerGrade ? 'nullable' : 'required', 'numeric', 'min:0'],
            'calculation_formula' => [$isCustom ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $method = PayrollComponentCalculationMethod::tryFrom((string) $this->input('calculation_method'));

            if ($method?->isPercentageOfBasicSalary() && (float) $this->input('global_rate') > 100) {
                $validator->errors()->add('global_rate', 'Percentage cannot be greater than 100%.');
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

    protected function rateSetPerGrade(?PayrollComponentCalculationMethod $method): bool
    {
        /** @var PayrollComponent $component */
        $component = $this->route('payroll_component');

        return $component->code === PayrollComponent::ATTENDANCE_ALLOWANCE_CODE
            && ($method?->isAttendanceAllowance() ?? false);
    }
}
