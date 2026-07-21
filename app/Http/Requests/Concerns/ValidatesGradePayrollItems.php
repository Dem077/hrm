<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PayrollComponentType;
use App\Models\PayrollComponent;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesGradePayrollItems
{
    /**
     * @return array<string, mixed>
     */
    protected function gradePayrollItemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.payroll_component_id' => ['required', 'integer', 'exists:payroll_components,id'],
            'items.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'items.*.loan_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'items.*.loan_bank' => ['nullable', 'string', 'max:50', Rule::exists('banks', 'code')->where('is_active', true)],
        ];
    }

    public function validateGradePayrollItems(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            $componentIds = collect($items)
                ->pluck('payroll_component_id')
                ->filter()
                ->unique()
                ->values();

            if ($componentIds->isEmpty()) {
                return;
            }

            $components = PayrollComponent::query()
                ->whereIn('id', $componentIds)
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $component = $components->get($item['payroll_component_id'] ?? null);

                if (! $component || $component->type !== PayrollComponentType::Loan) {
                    continue;
                }

                if (blank($item['loan_months'] ?? null)) {
                    $validator->errors()->add(
                        "items.{$index}.loan_months",
                        'Repayment period in months is required for loan components.',
                    );
                }

                if (blank($item['loan_bank'] ?? null)) {
                    $validator->errors()->add(
                        "items.{$index}.loan_bank",
                        'Bank is required for loan components.',
                    );
                }
            }
        });
    }
}
