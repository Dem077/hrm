<?php

use App\Enums\StructureGroupCode;
use App\Models\StructureGrade;
use App\Models\StructureGroup;
use App\Models\StructureLevel;
use App\Models\StructureNode;
use App\Services\Payroll\GradePayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('groups payroll items by calculation and type', function () {
    $service = app(GradePayrollService::class);

    $groups = $service->groupPayrollItems(collect([
        [
            'payroll_component_id' => 1,
            'name' => 'Basic Salary',
            'type' => 'addition',
            'calculation_method' => 'fixed',
            'is_mandatory' => true,
            'amount' => 50000,
        ],
        [
            'payroll_component_id' => 2,
            'name' => 'Attendance Pay',
            'type' => 'addition',
            'calculation_method' => 'daily',
            'is_mandatory' => true,
            'amount' => 250,
        ],
        [
            'payroll_component_id' => 3,
            'name' => 'Transport',
            'type' => 'addition',
            'calculation_method' => 'fixed',
            'is_mandatory' => false,
            'amount' => 5000,
        ],
        [
            'payroll_component_id' => 4,
            'name' => 'Tax',
            'type' => 'deduction',
            'calculation_method' => 'fixed',
            'is_mandatory' => false,
            'amount' => 2000,
        ],
    ]));

    expect($groups['mandatory'])->toHaveCount(1)
        ->and($groups['mandatory'][0]['name'])->toBe('Basic Salary');
    expect($groups['attendance_allowance'])->toHaveCount(1)
        ->and($groups['attendance_allowance'][0]['name'])->toBe('Attendance Pay');
    expect($groups['fixed_additions'])->toHaveCount(1);
    expect($groups['fixed_deductions'])->toHaveCount(1);
});

it('calculates fixed net excluding daily components', function () {
    $service = app(GradePayrollService::class);

    $totals = $service->calculateTotals(collect([
        [
            'type' => 'addition',
            'calculation_method' => 'fixed',
            'amount' => 50000,
        ],
        [
            'type' => 'addition',
            'calculation_method' => 'daily',
            'amount' => 250,
        ],
        [
            'type' => 'deduction',
            'calculation_method' => 'fixed',
            'amount' => 3000,
        ],
    ]));

    expect($totals['additions'])->toBe(50000.0);
    expect($totals['deductions'])->toBe(3000.0);
    expect($totals['net'])->toBe(47000.0);
    expect($totals['has_daily'])->toBeTrue();
    expect($totals['daily_count'])->toBe(1);
});

/**
 * @return array{node: StructureNode, level: StructureLevel, grade: StructureGrade}
 */
function createTestGrade(string $nodeName = 'IT', string $gradeCode = 'B', string $title = 'Manager'): array
{
    $group = StructureGroup::query()->where('code', StructureGroupCode::Department)->firstOrFail();

    $node = StructureNode::query()->create([
        'structure_group_id' => $group->id,
        'name' => $nodeName,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $level = StructureLevel::query()->create([
        'structure_node_id' => $node->id,
        'level_number' => 2,
        'reference_title' => 'Manager',
        'sort_order' => 0,
    ]);

    $grade = StructureGrade::query()->create([
        'structure_level_id' => $level->id,
        'grade' => $gradeCode,
        'title' => $title,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    return compact('node', 'level', 'grade');
}
