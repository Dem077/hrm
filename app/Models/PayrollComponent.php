<?php

namespace App\Models;

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'code',
    'type',
    'calculation_method',
    'global_rate',
    'is_mandatory',
    'sort_order',
    'is_active',
])]
class PayrollComponent extends Model
{
    public const BASIC_SALARY_CODE = 'basic_salary';

    public const LATE_FINE_CODE = 'late_fine';

    public const ABSENT_FEE_CODE = 'absent_fee';

    /**
     * @return list<string>
     */
    public static function systemCodes(): array
    {
        return [
            self::BASIC_SALARY_CODE,
            self::LATE_FINE_CODE,
            self::ABSENT_FEE_CODE,
        ];
    }

    protected function casts(): array
    {
        return [
            'type' => PayrollComponentType::class,
            'calculation_method' => PayrollComponentCalculationMethod::class,
            'global_rate' => 'decimal:2',
            'is_mandatory' => 'boolean',
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
        return $this->calculation_method->usesGlobalRate();
    }

    /**
     * @return list<PayrollComponentCalculationMethod>
     */
    public function allowedCalculationMethods(): array
    {
        return match ($this->code) {
            self::LATE_FINE_CODE => PayrollComponentCalculationMethod::lateFineOptions(),
            self::ABSENT_FEE_CODE => PayrollComponentCalculationMethod::absentFeeOptions(),
            default => [],
        };
    }

    public function isLoan(): bool
    {
        return $this->type->isLoan();
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
            'uses_global_rate' => $this->usesGlobalRate(),
            'is_percentage_rate' => $this->calculation_method->isPercentageOfBasicSalary(),
            'allowed_calculation_methods' => PayrollComponentCalculationMethod::optionsPayload(
                $this->allowedCalculationMethods(),
            ),
            'is_mandatory' => $this->is_mandatory,
            'is_system_mandatory' => $this->isSystemMandatory(),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'grades_count' => $this->grades_count ?? $this->grades()->count(),
        ];
    }
}
