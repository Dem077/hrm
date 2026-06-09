<?php

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Services\Payroll\DesignationPayrollService;
use Illuminate\Support\Collection;

it('groups payroll items by calculation and type', function () {
    $service = app(DesignationPayrollService::class);

    $groups = $service->groupPayrollItems(collect([
        [
            'payroll_component_id' => 1,
            'name' => 'Basic Salary',
            'type' => PayrollComponentType::Addition->value,
            'calculation_method' => PayrollComponentCalculationMethod::Fixed->value,
            'is_mandatory' => true,
            'amount' => 50000,
        ],
        [
            'payroll_component_id' => 2,
            'name' => 'Attendance Pay',
            'type' => PayrollComponentType::Addition->value,
            'calculation_method' => PayrollComponentCalculationMethod::Daily->value,
            'is_mandatory' => false,
            'amount' => 250,
        ],
        [
            'payroll_component_id' => 3,
            'name' => 'Transport',
            'type' => PayrollComponentType::Addition->value,
            'calculation_method' => PayrollComponentCalculationMethod::Fixed->value,
            'is_mandatory' => false,
            'amount' => 5000,
        ],
        [
            'payroll_component_id' => 4,
            'name' => 'Tax',
            'type' => PayrollComponentType::Deduction->value,
            'calculation_method' => PayrollComponentCalculationMethod::Fixed->value,
            'is_mandatory' => false,
            'amount' => 2000,
        ],
    ]));

    expect($groups['mandatory'])->toHaveCount(1);
    expect($groups['daily'])->toHaveCount(1);
    expect($groups['fixed_additions'])->toHaveCount(1);
    expect($groups['fixed_deductions'])->toHaveCount(1);
});

it('calculates fixed net excluding daily components', function () {
    $service = app(DesignationPayrollService::class);

    $totals = $service->calculateTotals(collect([
        [
            'type' => PayrollComponentType::Addition->value,
            'calculation_method' => PayrollComponentCalculationMethod::Fixed->value,
            'amount' => 50000,
        ],
        [
            'type' => PayrollComponentType::Addition->value,
            'calculation_method' => PayrollComponentCalculationMethod::Daily->value,
            'amount' => 250,
        ],
        [
            'type' => PayrollComponentType::Deduction->value,
            'calculation_method' => PayrollComponentCalculationMethod::Fixed->value,
            'amount' => 3000,
        ],
    ]));

    expect($totals['additions'])->toBe(50000.0);
    expect($totals['deductions'])->toBe(3000.0);
    expect($totals['net'])->toBe(47000.0);
    expect($totals['has_daily'])->toBeTrue();
    expect($totals['daily_count'])->toBe(1);
});
