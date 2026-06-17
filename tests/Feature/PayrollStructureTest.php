<?php

use App\Models\Designation;
use App\Models\PayrollComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('payroll-structure.view');
    Permission::findOrCreate('payroll-structure.update');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['payroll-structure.view', 'payroll-structure.update']);
});

it('shows payroll structure page with seeded basic salary component', function () {
    $response = $this->actingAs($this->user)->get('/payroll-structure');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('PayrollStructure/Index')
        ->has('components', 1)
        ->where('components.0.code', 'basic_salary')
        ->where('components.0.is_mandatory', true));
});

it('creates a designation with mandatory basic salary amount', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();

    $response = $this->actingAs($this->user)->post('/payroll-structure/designations', [
        'name' => 'Software Engineer',
        'code' => 'SE',
        'description' => 'Engineering role',
        'sort_order' => 1,
        'is_active' => true,
        'items' => [
            [
                'payroll_component_id' => $basicSalary->id,
                'amount' => 75000,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $designation = Designation::query()->where('code', 'SE')->firstOrFail();

    expect($designation->payrollComponents)->toHaveCount(1);
    expect((float) $designation->payrollComponents->first()->pivot->amount)->toBe(75000.0);
});

it('attaches a new mandatory component to existing designations', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();

    $this->actingAs($this->user)->post('/payroll-structure/designations', [
        'name' => 'Manager',
        'is_active' => true,
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

    $designation = Designation::query()->where('name', 'Manager')->firstOrFail();
    $hra = PayrollComponent::query()->where('name', 'House Rent Allowance')->firstOrFail();

    expect($designation->payrollComponents()->where('payroll_component_id', $hra->id)->exists())->toBeTrue();
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

    $response = $this->actingAs($this->user)->post('/payroll-structure/designations', [
        'name' => 'Field Staff',
        'is_active' => true,
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
            ->where('designations.0.totals.additions', 50000)
            ->where('designations.0.totals.net', 50000)
            ->where('designations.0.totals.has_daily', true)
            ->where('designations.0.items.1.calculation_method', 'daily')
            ->where('designations.0.items.1.amount', 250));
});

it('creates a designation with loan repayment details', function () {
    $basicSalary = PayrollComponent::query()->where('code', 'basic_salary')->firstOrFail();

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

    $response = $this->actingAs($this->user)->post('/payroll-structure/designations', [
        'name' => 'Accounts Officer',
        'is_active' => true,
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
            ->where('designations.0.items.1.type', 'loan')
            ->where('designations.0.items.1.amount', 3500)
            ->where('designations.0.items.1.loan_months', 24)
            ->where('designations.0.items.1.loan_bank', 'MIB')
            ->where('designations.0.totals.deductions', 3500)
            ->where('designations.0.totals.net', 36500));
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
