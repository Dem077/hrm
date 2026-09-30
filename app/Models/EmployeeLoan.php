<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'employee_id',
    'name',
    'monthly_amount',
    'loan_months',
    'loan_bank',
    'start_date',
    'notes',
    'is_active',
])]
class EmployeeLoan extends Model
{
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'loan_months' => 'integer',
            'start_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function totalAmount(): ?float
    {
        if ($this->loan_months === null) {
            return null;
        }

        return round((float) $this->monthly_amount * $this->loan_months, 2);
    }

    public function remainingMonths(?CarbonInterface $asOf = null): ?int
    {
        if ($this->loan_months === null) {
            return null;
        }

        $asOfDate = Carbon::parse($asOf ?? now())->startOfDay();
        $start = $this->start_date?->copy()->startOfDay();

        if ($start === null || $asOfDate->lt($start)) {
            return $this->loan_months;
        }

        $elapsed = (int) $start->diffInMonths($asOfDate);

        return max(0, $this->loan_months - $elapsed);
    }

    public function remainingAmount(?CarbonInterface $asOf = null): ?float
    {
        $remainingMonths = $this->remainingMonths($asOf);

        if ($remainingMonths === null) {
            return null;
        }

        return round((float) $this->monthly_amount * $remainingMonths, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        $this->loadMissing('employee:id,name,staff_id');

        $remainingMonths = $this->remainingMonths();
        $remainingAmount = $this->remainingAmount();
        $totalAmount = $this->totalAmount();

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'staff_id' => $this->employee->staff_id,
                'label' => "{$this->employee->name} ({$this->employee->staff_id})",
            ] : null,
            'name' => $this->name,
            'monthly_amount' => (float) $this->monthly_amount,
            'loan_months' => $this->loan_months,
            'total_amount' => $totalAmount,
            'remaining_months' => $remainingMonths,
            'remaining_amount' => $remainingAmount,
            'loan_bank' => $this->loan_bank,
            'loan_bank_label' => $this->loan_bank ? Bank::labelFor($this->loan_bank) : null,
            'start_date' => $this->start_date?->toDateString(),
            'notes' => $this->notes,
            'is_active' => $this->is_active,
        ];
    }
}
