<?php

namespace App\Services\Payroll;

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Enums\PayrollLoanBank;
use App\Models\Designation;
use App\Models\PayrollComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesignationPayrollService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForIndex(): array
    {
        return Designation::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Designation $designation) => $this->formatDesignation($designation))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{payroll_component_id: int, amount: float|int|string}>  $items
     */
    public function create(array $attributes, array $items): Designation
    {
        return DB::transaction(function () use ($attributes, $items) {
            $designation = Designation::query()->create($attributes);
            $this->syncPayrollItems($designation, $items);

            return $designation;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{payroll_component_id: int, amount: float|int|string}>  $items
     */
    public function update(Designation $designation, array $attributes, array $items): void
    {
        DB::transaction(function () use ($designation, $attributes, $items): void {
            $designation->update($attributes);
            $this->syncPayrollItems($designation, $items);
        });
    }

    public function delete(Designation $designation): void
    {
        $designation->delete();
    }

    /**
     * @param  list<array{payroll_component_id: int, amount: float|int|string}>  $items
     */
    public function syncPayrollItems(Designation $designation, array $items): void
    {
        $mandatoryIds = PayrollComponent::query()
            ->where('is_mandatory', true)
            ->pluck('id');

        $allowedIds = PayrollComponent::query()
            ->where('is_active', true)
            ->pluck('id');

        $sync = [];

        $componentsById = PayrollComponent::query()
            ->whereIn('id', collect($items)->pluck('payroll_component_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $componentId = (int) $item['payroll_component_id'];

            if (! $allowedIds->contains($componentId)) {
                continue;
            }

            $component = $componentsById->get($componentId);
            $pivot = [
                'amount' => round((float) $item['amount'], 2),
                'loan_months' => null,
                'loan_bank' => null,
            ];

            if ($component?->isLoan()) {
                $pivot['loan_months'] = (int) $item['loan_months'];
                $pivot['loan_bank'] = PayrollLoanBank::from($item['loan_bank'])->value;
            }

            $sync[$componentId] = $pivot;
        }

        foreach ($mandatoryIds as $componentId) {
            if (! array_key_exists($componentId, $sync)) {
                throw ValidationException::withMessages([
                    'items' => 'All mandatory payroll components must have an amount.',
                ]);
            }
        }

        $designation->payrollComponents()->sync($sync);
    }

    public function attachMandatoryComponentToAllDesignations(PayrollComponent $component): void
    {
        if (! $component->is_mandatory) {
            return;
        }

        Designation::query()->each(function (Designation $designation) use ($component): void {
            $designation->payrollComponents()->syncWithoutDetaching([
                $component->id => ['amount' => 0],
            ]);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function defaultItemsForNewDesignation(): array
    {
        return PayrollComponent::query()
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PayrollComponent $component) => $component->toPayrollItem(0))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDesignation(Designation $designation): array
    {
        $designation->load([
            'payrollComponents' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
        ]);

        $items = $designation->payrollComponents
            ->map(fn (PayrollComponent $component) => $this->formatComponentPivotItem($component))
            ->values()
            ->all();

        return [
            'id' => $designation->id,
            'name' => $designation->name,
            'code' => $designation->code,
            'description' => $designation->description,
            'sort_order' => $designation->sort_order,
            'is_active' => $designation->is_active,
            'items' => $items,
            'item_groups' => $this->groupPayrollItems(collect($items)),
            'totals' => $this->calculateTotals(collect($items)),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{
     *     mandatory: list<array<string, mixed>>,
     *     fixed_additions: list<array<string, mixed>>,
     *     fixed_deductions: list<array<string, mixed>>,
     *     attendance_allowance: list<array<string, mixed>>,
     *     loans: list<array<string, mixed>>
     * }
     */
    public function groupPayrollItems(Collection $items): array
    {
        return [
            'mandatory' => $items->where('is_mandatory', true)->values()->all(),
            'fixed_additions' => $items
                ->where('is_mandatory', false)
                ->where('type', PayrollComponentType::Addition->value)
                ->where('calculation_method', PayrollComponentCalculationMethod::Fixed->value)
                ->values()
                ->all(),
            'fixed_deductions' => $items
                ->where('is_mandatory', false)
                ->where('type', PayrollComponentType::Deduction->value)
                ->where('calculation_method', PayrollComponentCalculationMethod::Fixed->value)
                ->values()
                ->all(),
            'attendance_allowance' => $items
                ->filter(fn (array $item) => in_array(
                    $item['calculation_method'],
                    [
                        PayrollComponentCalculationMethod::Daily->value,
                        PayrollComponentCalculationMethod::Hourly->value,
                    ],
                    true
                ))
                ->values()
                ->all(),
            'loans' => $items
                ->where('type', PayrollComponentType::Loan->value)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{additions: float, deductions: float, net: float, has_attendance_allowance: bool, attendance_allowance_count: int, has_loans: bool, loan_count: int}
     */
    public function calculateTotals(Collection $items): array
    {
        $additions = $items
            ->where('type', PayrollComponentType::Addition->value)
            ->where('calculation_method', PayrollComponentCalculationMethod::Fixed->value)
            ->sum(fn (array $item) => (float) $item['amount']);

        $deductions = $items
            ->where('type', PayrollComponentType::Deduction->value)
            ->where('calculation_method', PayrollComponentCalculationMethod::Fixed->value)
            ->sum(fn (array $item) => (float) $item['amount']);

        $loanDeductions = $items
            ->where('type', PayrollComponentType::Loan->value)
            ->sum(fn (array $item) => (float) $item['amount']);

        $deductions += $loanDeductions;

        $attendanceAllowanceCount = $items
            ->filter(fn (array $item) => in_array(
                $item['calculation_method'],
                [
                    PayrollComponentCalculationMethod::Daily->value,
                    PayrollComponentCalculationMethod::Hourly->value,
                ],
                true
            ))
            ->count();

        $dailyCount = $items
            ->where('calculation_method', PayrollComponentCalculationMethod::Daily->value)
            ->count();

        $loanCount = $items->where('type', PayrollComponentType::Loan->value)->count();

        return [
            'additions' => round($additions, 2),
            'deductions' => round($deductions, 2),
            'net' => round($additions - $deductions, 2),
            'has_attendance_allowance' => $attendanceAllowanceCount > 0,
            'attendance_allowance_count' => $attendanceAllowanceCount,
            'has_daily' => $dailyCount > 0,
            'daily_count' => $dailyCount,
            'has_loans' => $loanCount > 0,
            'loan_count' => $loanCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatComponentPivotItem(PayrollComponent $component): array
    {
        $loanBank = filled($component->pivot->loan_bank)
            ? PayrollLoanBank::from($component->pivot->loan_bank)
            : null;

        return $component->toPayrollItem(
            (float) $component->pivot->amount,
            $component->pivot->loan_months !== null ? (int) $component->pivot->loan_months : null,
            $loanBank,
        );
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
            'description' => '',
            'sort_order' => 0,
            'is_active' => true,
            'items' => [],
        ];
    }
}
