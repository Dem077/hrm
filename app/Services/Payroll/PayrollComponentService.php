<?php

namespace App\Services\Payroll;

use App\Enums\PayrollComponentCalculationMethod;
use App\Models\PayrollComponent;
use Illuminate\Support\Facades\DB;

class PayrollComponentService
{
    public function __construct(
        protected GradePayrollService $gradePayrollService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForIndex(): array
    {
        return PayrollComponent::query()
            ->withCount('grades')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PayrollComponent $component) => $component->toPresentationArray())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PayrollComponent
    {
        return DB::transaction(function () use ($data) {
            $method = PayrollComponentCalculationMethod::tryFrom((string) ($data['calculation_method'] ?? ''));

            if ($method?->isCustomFormula()) {
                $data['global_rate'] = null;
            } else {
                $data['calculation_formula'] = null;
            }

            $component = PayrollComponent::query()->create($data);
            $this->gradePayrollService->attachMandatoryComponentToAllGrades($component);

            return $component;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PayrollComponent $component, array $data): ?string
    {
        if ($component->isSystemMandatory()) {
            return $component->name.' cannot be edited.';
        }

        if (
            isset($data['calculation_method'])
            && ($method = PayrollComponentCalculationMethod::tryFrom((string) $data['calculation_method']))
            && $method->usesGlobalRate()
            && ! $method->isCustomFormula()
        ) {
            return 'Late fine and absent fee calculation methods are reserved for system components.';
        }

        $wasMandatory = $component->is_mandatory;

        DB::transaction(function () use ($component, $data, $wasMandatory): void {
            if (
                isset($data['calculation_method'])
                && PayrollComponentCalculationMethod::tryFrom((string) $data['calculation_method'])?->isCustomFormula()
            ) {
                $data['global_rate'] = null;
            }

            $component->update($data);

            if (! $wasMandatory && $component->is_mandatory) {
                $this->gradePayrollService->attachMandatoryComponentToAllGrades($component);
            }
        });

        return null;
    }

    /**
     * @param  array{global_rate?: float|int|string|null, calculation_method: string, calculation_formula?: string|null}  $data
     */
    public function updateGlobalRate(PayrollComponent $component, array $data): ?string
    {
        if (! $component->isSystemMandatory() || $component->allowedCalculationMethods() === []) {
            return 'Only Late Fine and Absent Fee rates can be updated this way.';
        }

        $method = PayrollComponentCalculationMethod::from((string) $data['calculation_method']);
        $allowed = array_map(
            fn (PayrollComponentCalculationMethod $option) => $option->value,
            $component->allowedCalculationMethods(),
        );

        if (! in_array($method->value, $allowed, true)) {
            return 'That calculation method is not allowed for '.$component->name.'.';
        }

        $component->update([
            'calculation_method' => $method,
            'global_rate' => $method->isCustomFormula()
                ? null
                : round((float) ($data['global_rate'] ?? 0), 2),
            'calculation_formula' => $method->isCustomFormula()
                ? trim((string) ($data['calculation_formula'] ?? ''))
                : null,
        ]);

        return null;
    }

    public function delete(PayrollComponent $component): ?string
    {
        if ($component->isSystemMandatory()) {
            return $component->name.' cannot be deleted.';
        }

        if ($component->is_mandatory) {
            return 'Mandatory payroll components cannot be deleted. Remove the mandatory flag first or deactivate the component.';
        }

        if ($component->grades()->exists()) {
            return 'This component is assigned to grades and cannot be deleted. Deactivate it instead.';
        }

        $component->delete();

        return null;
    }

    public function createSuccessMessage(PayrollComponent $component): string
    {
        if ($component->is_mandatory) {
            return 'Payroll component created and added to all grades.';
        }

        return 'Payroll component created successfully.';
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyAttributes(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'type' => 'addition',
            'calculation_method' => 'fixed',
            'calculation_formula' => '',
            'formula_variables' => PayrollFormulaEvaluator::VARIABLES,
            'formula_variable_options' => PayrollFormulaEvaluator::variableOptions(),
            'is_mandatory' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
