<?php

namespace App\Services\Payroll;

use App\Enums\PayrollComponentType;
use App\Models\Designation;
use App\Models\PayrollComponent;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PayrollStructureService
{
    /**
     * @param  list<array{payroll_component_id: int, amount: float|int|string}>  $items
     */
    public function syncDesignationPayrollItems(Designation $designation, array $items): void
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
     * @return list<array{payroll_component_id: int, name: string, type: string, type_label: string, is_mandatory: bool, amount: float}>
     */
    public function defaultItemsForNewDesignation(): array
    {
        return PayrollComponent::query()
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PayrollComponent $component) => [
                'payroll_component_id' => $component->id,
                'name' => $component->name,
                'type' => $component->type->value,
                'type_label' => $component->type->label(),
                'is_mandatory' => true,
                'amount' => 0,
            ])
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

        $items = $designation->payrollComponents->map(fn (PayrollComponent $component) => [
            'payroll_component_id' => $component->id,
            'name' => $component->name,
            'type' => $component->type->value,
            'type_label' => $component->type->label(),
            'is_mandatory' => $component->is_mandatory,
            'amount' => (float) $component->pivot->amount,
        ])->values()->all();

        $totals = $this->calculateTotals(collect($items));

        return [
            'id' => $designation->id,
            'name' => $designation->name,
            'code' => $designation->code,
            'description' => $designation->description,
            'sort_order' => $designation->sort_order,
            'is_active' => $designation->is_active,
            'items' => $items,
            'totals' => $totals,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{additions: float, deductions: float, net: float}
     */
    public function calculateTotals(Collection $items): array
    {
        $additions = $items
            ->where('type', PayrollComponentType::Addition->value)
            ->sum(fn (array $item) => (float) $item['amount']);

        $deductions = $items
            ->where('type', PayrollComponentType::Deduction->value)
            ->sum(fn (array $item) => (float) $item['amount']);

        return [
            'additions' => round($additions, 2),
            'deductions' => round($deductions, 2),
            'net' => round($additions - $deductions, 2),
        ];
    }
}
