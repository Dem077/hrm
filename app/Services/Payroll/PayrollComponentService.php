<?php

namespace App\Services\Payroll;

use App\Enums\EmploymentType;
use App\Enums\PayrollApplicabilityField;
use App\Enums\PayrollApplicabilityOperator;
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

            $data['applicability_rules'] = $this->normalizeApplicabilityRules($data['applicability_rules'] ?? null);

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
            return 'Late fine, absent fee, overtime, and attendance allowance calculation methods are reserved for system components.';
        }

        $wasMandatory = $component->is_mandatory;

        DB::transaction(function () use ($component, $data, $wasMandatory): void {
            if (
                isset($data['calculation_method'])
                && PayrollComponentCalculationMethod::tryFrom((string) $data['calculation_method'])?->isCustomFormula()
            ) {
                $data['global_rate'] = null;
            }

            if (array_key_exists('applicability_rules', $data)) {
                $data['applicability_rules'] = $this->normalizeApplicabilityRules($data['applicability_rules']);
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
            return 'Only system company-rate components can be updated this way.';
        }

        $method = PayrollComponentCalculationMethod::from((string) $data['calculation_method']);
        $allowed = array_map(
            fn (PayrollComponentCalculationMethod $option) => $option->value,
            $component->allowedCalculationMethods(),
        );

        if (! in_array($method->value, $allowed, true)) {
            return 'That calculation method is not allowed for '.$component->name.'.';
        }

        $rateSetPerGrade = $component->code === PayrollComponent::ATTENDANCE_ALLOWANCE_CODE
            && $method->isAttendanceAllowance();

        $component->update([
            'calculation_method' => $method,
            'global_rate' => ($method->isCustomFormula() || $rateSetPerGrade)
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
            'applicability_rules' => ['all' => []],
            'applicability_summary' => [],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * @param  mixed  $rules
     * @return array{all: list<array{field: string, operator: string, value: string}>}|null
     */
    public function normalizeApplicabilityRules(mixed $rules): ?array
    {
        if (! is_array($rules) || ! isset($rules['all']) || ! is_array($rules['all'])) {
            return null;
        }

        $normalized = [];
        $seen = [];

        foreach ($rules['all'] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $field = PayrollApplicabilityField::tryFrom((string) ($rule['field'] ?? ''));
            $operator = PayrollApplicabilityOperator::tryFrom((string) ($rule['operator'] ?? PayrollApplicabilityOperator::Equals->value))
                ?? PayrollApplicabilityOperator::Equals;
            $value = trim((string) ($rule['value'] ?? ''));

            if (! $field || $value === '') {
                continue;
            }

            if ($field === PayrollApplicabilityField::EmploymentType
                && EmploymentType::tryFrom($value) === null
            ) {
                continue;
            }

            $key = $field->value.'|'.$operator->value.'|'.mb_strtolower($value);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = [
                'field' => $field->value,
                'operator' => $operator->value,
                'value' => $value,
            ];
        }

        return $normalized === [] ? null : ['all' => $normalized];
    }
}
