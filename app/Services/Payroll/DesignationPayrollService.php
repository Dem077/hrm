<?php

namespace App\Services\Payroll;

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
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

        foreach ($items as $item) {
            $componentId = (int) $item['payroll_component_id'];

            if (! $allowedIds->contains($componentId)) {
                continue;
            }

            $sync[$componentId] = [
                'amount' => round((float) $item['amount'], 2),
            ];
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
            ->map(fn (PayrollComponent $component) => $component->toPayrollItem((float) $component->pivot->amount))
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
     *     daily: list<array<string, mixed>>
     * }
     */
    public function groupPayrollItems(Collection $items): array
    {
        return [
            'mandatory' => $items->where('is_mandatory', true)->values()->all(),
            'fixed_additions' => $items
                ->where('is_mandatory', false)
                ->where('calculation_method', PayrollComponentCalculationMethod::Fixed->value)
                ->where('type', PayrollComponentType::Addition->value)
                ->values()
                ->all(),
            'fixed_deductions' => $items
                ->where('is_mandatory', false)
                ->where('calculation_method', PayrollComponentCalculationMethod::Fixed->value)
                ->where('type', PayrollComponentType::Deduction->value)
                ->values()
                ->all(),
            'daily' => $items
                ->where('calculation_method', PayrollComponentCalculationMethod::Daily->value)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{additions: float, deductions: float, net: float, has_daily: bool, daily_count: int}
     */
    public function calculateTotals(Collection $items): array
    {
        $fixedItems = $items->where(
            'calculation_method',
            PayrollComponentCalculationMethod::Fixed->value,
        );

        $additions = $fixedItems
            ->where('type', PayrollComponentType::Addition->value)
            ->sum(fn (array $item) => (float) $item['amount']);

        $deductions = $fixedItems
            ->where('type', PayrollComponentType::Deduction->value)
            ->sum(fn (array $item) => (float) $item['amount']);

        $dailyCount = $items->where(
            'calculation_method',
            PayrollComponentCalculationMethod::Daily->value,
        )->count();

        return [
            'additions' => round($additions, 2),
            'deductions' => round($deductions, 2),
            'net' => round($additions - $deductions, 2),
            'has_daily' => $dailyCount > 0,
            'daily_count' => $dailyCount,
        ];
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
