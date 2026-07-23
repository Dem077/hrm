<?php

namespace App\Services\Payroll;

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Models\Bank;
use App\Models\PayrollComponent;
use App\Models\StructureGrade;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GradePayrollService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForIndex(): array
    {
        $this->backfillMissingMandatoryComponents();

        $grades = StructureGrade::query()
            ->with([
                'payrollComponents' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                'level.group',
                'level.node.group',
                'level.node.parent',
            ])
            ->get()
            ->map(fn (StructureGrade $grade) => $this->formatGrade($grade))
            ->sort(function (array $a, array $b): int {
                $groupOrder = [
                    'strategic_leadership' => 0,
                    'division' => 1,
                    'department' => 2,
                    'unit_section' => 3,
                ];

                $aGroup = $groupOrder[$a['group']['code'] ?? ''] ?? 99;
                $bGroup = $groupOrder[$b['group']['code'] ?? ''] ?? 99;
                if ($aGroup !== $bGroup) {
                    return $aGroup <=> $bGroup;
                }

                $aNode = $a['node']['name'] ?? '';
                $bNode = $b['node']['name'] ?? '';
                if ($aNode !== $bNode) {
                    return strcasecmp($aNode, $bNode);
                }

                $aLevel = (int) ($a['level']['level_number'] ?? 0);
                $bLevel = (int) ($b['level']['level_number'] ?? 0);
                if ($aLevel !== $bLevel) {
                    return $bLevel <=> $aLevel;
                }

                $aSort = (int) ($a['sort_order'] ?? 0);
                $bSort = (int) ($b['sort_order'] ?? 0);
                if ($aSort !== $bSort) {
                    return $aSort <=> $bSort;
                }

                return strcasecmp((string) $a['grade'], (string) $b['grade']);
            })
            ->values()
            ->all();

        return $grades;
    }

    public function attachMandatoryComponents(StructureGrade $grade): void
    {
        $attach = [];

        PayrollComponent::query()
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (PayrollComponent $component) use (&$attach): void {
                $attach[$component->id] = [
                    'amount' => 0,
                    'loan_months' => null,
                    'loan_bank' => null,
                ];
            });

        if ($attach !== []) {
            $grade->payrollComponents()->syncWithoutDetaching($attach);
        }
    }

    public function backfillMissingMandatoryComponents(): void
    {
        $mandatoryIds = PayrollComponent::query()
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->pluck('id');

        if ($mandatoryIds->isEmpty()) {
            return;
        }

        foreach ($mandatoryIds as $componentId) {
            StructureGrade::query()
                ->whereDoesntHave(
                    'payrollComponents',
                    fn ($query) => $query->where('payroll_components.id', $componentId),
                )
                ->orderBy('id')
                ->chunkById(100, function ($grades) use ($componentId): void {
                    foreach ($grades as $grade) {
                        $grade->payrollComponents()->syncWithoutDetaching([
                            $componentId => [
                                'amount' => 0,
                                'loan_months' => null,
                                'loan_bank' => null,
                            ],
                        ]);
                    }
                });
        }
    }

    /**
     * @param  list<array{payroll_component_id: int, amount: float|int|string}>  $items
     */
    public function updatePackage(StructureGrade $grade, array $items): void
    {
        $this->syncPayrollItems($grade, $items);
    }

    /**
     * @param  list<array{payroll_component_id: int, amount: float|int|string}>  $items
     */
    public function syncPayrollItems(StructureGrade $grade, array $items): void
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
                $pivot['loan_bank'] = (string) $item['loan_bank'];
            }

            $sync[$componentId] = $pivot;
        }

        foreach ($mandatoryIds as $componentId) {
            if (! array_key_exists($componentId, $sync)) {
                $mandatoryComponent = PayrollComponent::query()->find($componentId);

                if ($mandatoryComponent?->usesGlobalRate()) {
                    $sync[$componentId] = [
                        'amount' => 0,
                        'loan_months' => null,
                        'loan_bank' => null,
                    ];

                    continue;
                }

                throw ValidationException::withMessages([
                    'items' => 'All mandatory payroll components must have an amount.',
                ]);
            }
        }

        $grade->payrollComponents()->sync($sync);
    }

    public function attachMandatoryComponentToAllGrades(PayrollComponent $component): void
    {
        if (! $component->is_mandatory) {
            return;
        }

        StructureGrade::query()->each(function (StructureGrade $grade) use ($component): void {
            $grade->payrollComponents()->syncWithoutDetaching([
                $component->id => ['amount' => 0],
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function formatGrade(StructureGrade $grade): array
    {
        $grade->load([
            'payrollComponents' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'level.group',
            'level.node.group',
            'level.node.parent',
        ]);

        $path = $grade->resolvePath();

        $items = $grade->payrollComponents
            ->map(fn (PayrollComponent $component) => $this->formatComponentPivotItem($component))
            ->values()
            ->all();

        $items = $this->withMandatoryItems($items);

        return [
            'id' => $grade->id,
            'grade' => $grade->grade,
            'title' => $grade->title,
            'label' => $grade->label(),
            'path_label' => $path['path_label'],
            'group' => $path['group'],
            'node' => $path['node'],
            'level' => $path['level'],
            'sort_order' => $grade->sort_order,
            'is_active' => $grade->is_active,
            'items' => $items,
            'item_groups' => $this->groupPayrollItems(collect($items)),
            'totals' => $this->calculateTotals(collect($items)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function withMandatoryItems(array $items): array
    {
        $existingIds = collect($items)->pluck('payroll_component_id')->map(fn ($id) => (int) $id);

        $missing = PayrollComponent::query()
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (PayrollComponent $component) => ! $existingIds->contains($component->id))
            ->map(fn (PayrollComponent $component) => $component->toPayrollItem(0))
            ->values()
            ->all();

        if ($missing === []) {
            return $items;
        }

        return array_values(array_merge($missing, $items));
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
        $isAttendanceAllowance = fn (array $item): bool => in_array(
            $item['calculation_method'],
            [
                PayrollComponentCalculationMethod::Daily->value,
                PayrollComponentCalculationMethod::Hourly->value,
            ],
            true,
        );

        $usesGlobalRate = fn (array $item): bool => in_array(
            $item['calculation_method'],
            [
                PayrollComponentCalculationMethod::PerLateMinute->value,
                PayrollComponentCalculationMethod::PerLateMinuteOfBasic->value,
                PayrollComponentCalculationMethod::PerAbsentDay->value,
                PayrollComponentCalculationMethod::PerAbsentDayOfBasic->value,
                PayrollComponentCalculationMethod::CustomFormula->value,
            ],
            true,
        );

        return [
            'mandatory' => $items
                ->where('is_mandatory', true)
                ->reject(fn (array $item) => $isAttendanceAllowance($item) || $usesGlobalRate($item) || $item['type'] === PayrollComponentType::Loan->value)
                ->values()
                ->all(),
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
                ->filter($isAttendanceAllowance)
                ->values()
                ->all(),
            'company_penalties' => $items
                ->filter($usesGlobalRate)
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

    protected function formatComponentPivotItem(PayrollComponent $component): array
    {
        $loanBank = filled($component->pivot->loan_bank)
            ? (string) $component->pivot->loan_bank
            : null;

        return $component->toPayrollItem(
            (float) $component->pivot->amount,
            $component->pivot->loan_months !== null ? (int) $component->pivot->loan_months : null,
            $loanBank,
            $loanBank ? Bank::labelFor($loanBank) : null,
        );
    }
}
