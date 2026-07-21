<?php

namespace App\Models;

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Models\Bank;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'code',
    'type',
    'calculation_method',
    'is_mandatory',
    'sort_order',
    'is_active',
])]
class PayrollComponent extends Model
{
    public const BASIC_SALARY_CODE = 'basic_salary';

    protected function casts(): array
    {
        return [
            'type' => PayrollComponentType::class,
            'calculation_method' => PayrollComponentCalculationMethod::class,
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
        return $this->code === self::BASIC_SALARY_CODE;
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
            'amount' => $amount,
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
            'is_mandatory' => $this->is_mandatory,
            'is_system_mandatory' => $this->isSystemMandatory(),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'grades_count' => $this->grades_count ?? $this->grades()->count(),
        ];
    }
}
