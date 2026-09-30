<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PayrollComponentType;
use App\Models\PayrollComponent;
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

                if ($component?->type === PayrollComponentType::Loan) {
                    $validator->errors()->add(
                        "items.{$index}.payroll_component_id",
                        'Loans are managed per employee under Employee Loans, not on designations.',
                    );
                }
            }
        });
    }
}
