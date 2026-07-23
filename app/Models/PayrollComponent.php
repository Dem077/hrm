<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\PayrollApplicabilityField;
use App\Enums\PayrollApplicabilityOperator;
use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Services\Payroll\PayrollFormulaEvaluator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'code',
    'type',
    'calculation_method',
    'global_rate',
    'calculation_formula',
    'is_mandatory',
    'applicability_rules',
    'sort_order',
    'is_active',
])]
class PayrollComponent extends Model
{
    public const BASIC_SALARY_CODE = 'basic_salary';

    public const LATE_FINE_CODE = 'late_fine';

    public const ABSENT_FEE_CODE = 'absent_fee';

    public const OVERTIME_CODE = 'overtime';

    public const ATTENDANCE_ALLOWANCE_CODE = 'attendance_allowance';

    /**
     * @return list<string>
     */
    public static function systemCodes(): array
    {
        return [
            self::BASIC_SALARY_CODE,
            self::LATE_FINE_CODE,
            self::ABSENT_FEE_CODE,
            self::OVERTIME_CODE,
            self::ATTENDANCE_ALLOWANCE_CODE,
        ];
    }

    /**
     * System components that always use a company-wide rate/formula.
     *
     * @return list<string>
     */
    public static function globalRateCodes(): array
    {
        return [
            self::LATE_FINE_CODE,
            self::ABSENT_FEE_CODE,
            self::OVERTIME_CODE,
        ];
    }

    protected function casts(): array
    {
        return [
            'type' => PayrollComponentType::class,
            'calculation_method' => PayrollComponentCalculationMethod::class,
            'global_rate' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'applicability_rules' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(StructureGrade::class, 'grade_payroll_component')
            ->withPivot(['amount', 'loan_months', 'loan_bank'])
            ->withTimestamps();
    }

    public function isSystemMandatory(): bool
    {
        return in_array($this->code, self::systemCodes(), true);
    }

    public function usesGlobalRate(): bool
    {
        // Days attended / hours worked: rate is set per grade like normal components.
        // Custom formula: company-wide, same pattern as overtime.
        if ($this->code === self::ATTENDANCE_ALLOWANCE_CODE) {
            return $this->calculation_method->isCustomFormula();
        }

        return $this->calculation_method->usesGlobalRate()
            || in_array($this->code, self::globalRateCodes(), true);
    }

    public function isConfigurableSystemComponent(): bool
    {
        return $this->allowedCalculationMethods() !== [];
    }

    /**
     * @return list<PayrollComponentCalculationMethod>
     */
    public function allowedCalculationMethods(): array
    {
        return match ($this->code) {
            self::LATE_FINE_CODE => PayrollComponentCalculationMethod::lateFineOptions(),
            self::ABSENT_FEE_CODE => PayrollComponentCalculationMethod::absentFeeOptions(),
            self::OVERTIME_CODE => PayrollComponentCalculationMethod::overtimeOptions(),
            self::ATTENDANCE_ALLOWANCE_CODE => PayrollComponentCalculationMethod::attendanceAllowanceOptions(),
            default => [],
        };
    }

    public function isLoan(): bool
    {
        return $this->type->isLoan();
    }

    public function hasApplicabilityRules(): bool
    {
        $rules = $this->applicability_rules;

        return is_array($rules)
            && isset($rules['all'])
            && is_array($rules['all'])
            && $rules['all'] !== [];
    }

    /**
     * Whether this component should be applied to the employee during payroll.
     * Empty rules mean everyone.
     */
    public function appliesToEmployee(Employee $employee): bool
    {
        if (! $this->hasApplicabilityRules()) {
            return true;
        }

        foreach ($this->applicability_rules['all'] as $rule) {
            if (! is_array($rule)) {
                return false;
            }

            $field = PayrollApplicabilityField::tryFrom((string) ($rule['field'] ?? ''));
            $operator = PayrollApplicabilityOperator::tryFrom((string) ($rule['operator'] ?? PayrollApplicabilityOperator::Equals->value))
                ?? PayrollApplicabilityOperator::Equals;
            $expected = trim((string) ($rule['value'] ?? ''));

            if (! $field || $expected === '') {
                continue;
            }

            $actual = match ($field) {
                PayrollApplicabilityField::Nationality => trim((string) ($employee->nationality ?? '')),
                PayrollApplicabilityField::EmploymentType => $employee->employment_type instanceof EmploymentType
                    ? $employee->employment_type->value
                    : trim((string) ($employee->employment_type ?? '')),
            };

            $matches = $actual !== '' && strcasecmp($actual, $expected) === 0;

            $passes = match ($operator) {
                PayrollApplicabilityOperator::Equals => $matches,
                PayrollApplicabilityOperator::NotEquals => ! $matches,
            };

            if (! $passes) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{field: string, field_label: string, operator: string, operator_label: string, operator_symbol: string, value: string, value_label: string}>
     */
    public function applicabilityRulesPresentation(): array
    {
        if (! $this->hasApplicabilityRules()) {
            return [];
        }

        $rows = [];

        foreach ($this->applicability_rules['all'] as $rule) {
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

            $valueLabel = $value;

            if ($field === PayrollApplicabilityField::EmploymentType) {
                $valueLabel = EmploymentType::tryFrom($value)?->label() ?? $value;
            }

            $rows[] = [
                'field' => $field->value,
                'field_label' => $field->label(),
                'operator' => $operator->value,
                'operator_label' => $operator->label(),
                'operator_symbol' => $operator->symbol(),
                'value' => $value,
                'value_label' => $valueLabel,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayrollItem(float $amount = 0, ?int $loanMonths = null, ?string $loanBank = null, ?string $loanBankLabel = null): array
    {
        $item = [
            'payroll_component_id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'calculation_method' => $this->calculation_method->value,
            'calculation_method_label' => $this->calculation_method->label(),
            'amount_label' => $this->isLoan()
                ? 'Monthly payment'
                : $this->calculation_method->amountLabel(),
            'is_mandatory' => $this->is_mandatory,
            'uses_global_rate' => $this->usesGlobalRate(),
            'is_percentage_rate' => $this->calculation_method->isPercentageOfBasicSalary(),
            'global_rate' => $this->usesGlobalRate() ? (float) ($this->global_rate ?? 0) : null,
            'calculation_formula' => $this->calculation_method->isCustomFormula()
                ? $this->calculation_formula
                : null,
            'amount' => $this->usesGlobalRate() ? (float) ($this->global_rate ?? 0) : $amount,
            'loan_months' => null,
            'loan_bank' => null,
            'loan_bank_label' => null,
        ];

        if ($this->isLoan()) {
            $item['loan_months'] = $loanMonths;
            $item['loan_bank'] = $loanBank;
            $item['loan_bank_label'] = $loanBankLabel ?? ($loanBank ? Bank::labelFor($loanBank) : null);
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'calculation_method' => $this->calculation_method->value,
            'calculation_method_label' => $this->calculation_method->label(),
            'amount_label' => $this->calculation_method->amountLabel(),
            'global_rate' => $this->usesGlobalRate() ? (float) ($this->global_rate ?? 0) : null,
            'calculation_formula' => $this->calculation_formula,
            'uses_global_rate' => $this->usesGlobalRate(),
            'is_percentage_rate' => $this->calculation_method->isPercentageOfBasicSalary(),
            'is_custom_formula' => $this->calculation_method->isCustomFormula(),
            'is_configurable' => $this->isConfigurableSystemComponent(),
            'rate_set_per_grade' => $this->code === self::ATTENDANCE_ALLOWANCE_CODE
                && $this->calculation_method->isAttendanceAllowance(),
            'allowed_calculation_methods' => PayrollComponentCalculationMethod::optionsPayload(
                $this->allowedCalculationMethods(),
            ),
            'formula_variables' => PayrollFormulaEvaluator::VARIABLES,
            'formula_variable_options' => PayrollFormulaEvaluator::variableOptions(),
            'is_mandatory' => $this->is_mandatory,
            'is_system_mandatory' => $this->isSystemMandatory(),
            'applicability_rules' => [
                'all' => collect($this->applicabilityRulesPresentation())
                    ->map(fn (array $rule) => [
                        'field' => $rule['field'],
                        'operator' => $rule['operator'],
                        'value' => $rule['value'],
                    ])
                    ->values()
                    ->all(),
            ],
            'applicability_summary' => $this->applicabilityRulesPresentation(),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'grades_count' => $this->grades_count ?? $this->grades()->count(),
        ];
    }
}
