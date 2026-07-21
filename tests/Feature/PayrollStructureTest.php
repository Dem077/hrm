<?php

use App\Enums\StructureGroupCode;
use App\Models\PayrollComponent;
use App\Models\StructureGrade;
use App\Models\StructureGroup;
use App\Models\StructureLevel;
use App\Models\StructureNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('payroll-structure.view');
    Permission::findOrCreate('payroll-structure.update');
    Permission::findOrCreate('company-structure.create');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'payroll-structure.view',
        'payroll-structure.update',
        'company-structure.create',
    ]);
});

function makeGradeForPayrollTests(string $title = 'Manager'): StructureGrade
{
    $group = StructureGroup::query()->where('code', StructureGroupCode::Department)->firstOrFail();
    $node = StructureNode::query()->create([
        'structure_group_id' => $group->id,
        'name' => 'IT',
        'is_active' => true,
    ]);
    $level = StructureLevel::query()->create([
        'structure_node_id' => $node->id,
        'level_number' => 2,
        'reference_title' => 'Manager',
    ]);

    return StructureGrade::query()->create([
        'structure_level_id' => $level->id,
        'grade' => 'B',
        'title' => $title,
        'is_active' => true,
    ]);
}

it('shows payroll structure page with seeded basic salary component', function () {
    $response = $this->actingAs($this->user)->get('/payroll-structure');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PayrollStructure/Index')
        ->has('components', 1)
        ->where('components.0.code', 'basic_salary')
        ->where('components.0.is_mandatory', true)
        ->has('grades'));
});

it('updates a grade payroll package with mandatory basic salary amount', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();
    $grade = makeGradeForPayrollTests('Software Engineer');

    $response = $this->actingAs($this->user)->put('/payroll-structure/grades/'.$grade->id, [
        'items' => [
            [
                'payroll_component_id' => $basicSalary->id,
                'amount' => 75000,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($grade->fresh()->payrollComponents)->toHaveCount(1);
    expect((float) $grade->fresh()->payrollComponents->first()->pivot->amount)->toBe(75000.0);
});

it('attaches a new mandatory component to existing grades', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();
    $grade = makeGradeForPayrollTests('Manager');

    $this->actingAs($this->user)->put('/payroll-structure/grades/'.$grade->id, [
        'items' => [
            [
                'payroll_component_id' => $basicSalary->id,
                'amount' => 100000,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)->post('/payroll-structure/components', [
        'name' => 'House Rent Allowance',
        'type' => 'addition',
        'calculation_method' => 'fixed',
        'is_mandatory' => true,
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $hra = PayrollComponent::query()->where('name', 'House Rent Allowance')->firstOrFail();

    expect($grade->payrollComponents()->where('payroll_component_id', $hra->id)->exists())->toBeTrue();
});

it('prevents deleting basic salary', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();

    $response = $this->actingAs($this->user)->delete('/payroll-structure/components/'.$basicSalary->id);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(PayrollComponent::query()->where('code', 'basic_salary')->exists())->toBeTrue();
});

it('supports daily rate components excluded from fixed net', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();
    $grade = makeGradeForPayrollTests('Field Staff');

    $this->actingAs($this->user)->post('/payroll-structure/components', [
        'name' => 'Attendance Pay',
        'code' => 'attendance_pay',
        'type' => 'addition',
        'calculation_method' => 'daily',
        'is_mandatory' => false,
        'sort_order' => 5,
        'is_active' => true,
    ]);

    $attendancePay = PayrollComponent::query()->where('code', 'attendance_pay')->firstOrFail();

    $response = $this->actingAs($this->user)->put('/payroll-structure/grades/'.$grade->id, [
        'items' => [
            [
                'payroll_component_id' => $basicSalary->id,
                'amount' => 50000,
            ],
            [
                'payroll_component_id' => $attendancePay->id,
                'amount' => 250,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->actingAs($this->user)
        ->get('/payroll-structure')
        ->assertInertia(fn ($page) => $page
            ->where('grades.0.totals.additions', 50000)
            ->where('grades.0.totals.net', 50000)
            ->where('grades.0.totals.has_daily', true)
            ->where('grades.0.items.1.calculation_method', 'daily')
            ->where('grades.0.items.1.amount', 250));
});

it('supports hourly attendance allowance components excluded from fixed net', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();
    $grade = makeGradeForPayrollTests('Support Staff');

    $this->actingAs($this->user)->post('/payroll-structure/components', [
        'name' => 'Attendance Allowance',
        'code' => 'attendance_allowance',
        'type' => 'addition',
        'calculation_method' => 'hourly',
        'is_mandatory' => false,
        'sort_order' => 6,
        'is_active' => true,
    ]);

    $attendanceAllowance = PayrollComponent::query()->where('code', 'attendance_allowance')->firstOrFail();

    $response = $this->actingAs($this->user)->put('/payroll-structure/grades/'.$grade->id, [
        'items' => [
            [
                'payroll_component_id' => $basicSalary->id,
                'amount' => 30000,
            ],
            [
                'payroll_component_id' => $attendanceAllowance->id,
                'amount' => 120,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->actingAs($this->user)
        ->get('/payroll-structure')
        ->assertInertia(fn ($page) => $page
            ->where('grades.0.totals.additions', 30000)
            ->where('grades.0.totals.net', 30000)
            ->where('grades.0.totals.has_attendance_allowance', true)
            ->where('grades.0.items.1.calculation_method', 'hourly')
            ->where('grades.0.items.1.amount', 120));
});

it('creates a grade package with loan repayment details', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();
    $grade = makeGradeForPayrollTests('Accounts Officer');

    $this->actingAs($this->user)->post('/payroll-structure/components', [
        'name' => 'Staff Loan',
        'code' => 'staff_loan',
        'type' => 'loan',
        'calculation_method' => 'fixed',
        'is_mandatory' => false,
        'sort_order' => 20,
        'is_active' => true,
    ]);

    $loan = PayrollComponent::query()->where('code', 'staff_loan')->firstOrFail();

    $response = $this->actingAs($this->user)->put('/payroll-structure/grades/'.$grade->id, [
        'items' => [
            [
                'payroll_component_id' => $basicSalary->id,
                'amount' => 40000,
            ],
            [
                'payroll_component_id' => $loan->id,
                'amount' => 3500,
                'loan_months' => 24,
                'loan_bank' => 'MIB',
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->actingAs($this->user)
        ->get('/payroll-structure')
        ->assertInertia(fn ($page) => $page
            ->where('grades.0.items.1.type', 'loan')
            ->where('grades.0.items.1.amount', 3500)
            ->where('grades.0.items.1.loan_months', 24)
            ->where('grades.0.items.1.loan_bank', 'MIB')
            ->where('grades.0.totals.deductions', 3500)
            ->where('grades.0.totals.net', 36500));
});

it('prevents editing basic salary', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();

    $response = $this->actingAs($this->user)->put('/payroll-structure/components/'.$basicSalary->id, [
        'name' => 'Renamed Salary',
        'type' => 'deduction',
        'is_mandatory' => false,
        'sort_order' => 99,
        'is_active' => false,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($basicSalary->fresh()->name)->toBe('Basic Salary');
});
