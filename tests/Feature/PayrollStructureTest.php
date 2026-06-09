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
