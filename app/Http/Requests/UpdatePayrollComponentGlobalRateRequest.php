<?php

namespace App\Http\Requests;

use App\Enums\PayrollComponentCalculationMethod;
use App\Models\PayrollComponent;
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
                redirect()->back()->with('error', 'Only Late Fine and Absent Fee rates can be updated this way.')
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

        $allowed = array_map(
            fn (PayrollComponentCalculationMethod $method) => $method->value,
            $component->allowedCalculationMethods(),
        );

        return [
            'calculation_method' => ['required', 'string', Rule::in($allowed)],
            'global_rate' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $method = PayrollComponentCalculationMethod::tryFrom((string) $this->input('calculation_method'));

            if ($method?->isPercentageOfBasicSalary() && (float) $this->input('global_rate') > 100) {
                $validator->errors()->add('global_rate', 'Percentage cannot be greater than 100%.');
            }
        });
    }
}
