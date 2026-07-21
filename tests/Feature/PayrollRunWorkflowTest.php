<?php

use App\Enums\StructureGroupCode;
use App\Models\Employee;
use App\Models\StructureGrade;
use App\Models\StructureGroup;
use App\Models\StructureLevel;
use App\Models\StructureNode;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function payrollTestUser(): User
{
    return User::query()->firstOrFail();
}

beforeEach(function () {
    foreach (PermissionRegistry::all() as $permission) {
        Permission::findOrCreate($permission);
    }

    $role = Role::findOrCreate('Payroll Admin');
    $role->syncPermissions([
        'payroll.view',
        'payroll.create',
        'payroll.delete',
        'payroll.adjust',
        'payroll.process',
        'payroll.finalize',
        'payroll.export',
        'payroll.audit.view',
    ]);

    $user = User::factory()->create([
        'password' => bcrypt('secret-123'),
    ]);
    $user->assignRole($role);

    $group = StructureGroup::query()->where('code', StructureGroupCode::Department)->firstOrFail();
    $node = StructureNode::query()->create([
        'structure_group_id' => $group->id,
        'name' => 'HR',
        'is_active' => true,
    ]);
    $level = StructureLevel::query()->create([
        'structure_node_id' => $node->id,
        'level_number' => 1,
        'reference_title' => 'Officer',
    ]);
    $grade = StructureGrade::query()->create([
        'structure_level_id' => $level->id,
        'grade' => 'C',
        'title' => 'Officer',
        'is_active' => true,
    ]);

    Employee::query()->create([
        'staff_id' => 'P001',
        'name' => 'Payroll One',
        'national_id' => 'NID-001',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'is_active' => true,
        'grade_id' => $grade->id,
        'bank_name' => 'Test Bank',
        'account_name' => 'Payroll One',
        'account_no' => '123456',
    ]);
});

it('creates custom draft run and blocks overlap', function () {
    $user = payrollTestUser();

    $this->actingAs($user)
        ->post('/payroll', [
            'period_source' => 'custom',
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $runId = \App\Models\PayrollRun::query()->value('id');
    expect($runId)->not->toBeNull();

    $this->actingAs($user)
        ->from('/payroll')
        ->post('/payroll', [
            'period_source' => 'custom',
            'from' => '2026-07-15',
            'to' => '2026-08-10',
        ])
        ->assertRedirect('/payroll')
        ->assertSessionHasErrors('period');
});

it('processes, finalises, and reopens with password', function () {
    $user = payrollTestUser();

    $this->actingAs($user)
        ->post('/payroll', [
            'period_source' => 'custom',
            'from' => '2026-06-01',
            'to' => '2026-06-30',
        ]);

    $runId = \App\Models\PayrollRun::query()->value('id');
    expect($runId)->not->toBeNull();

    $this->actingAs($user)
        ->post("/payroll/{$runId}/process")
        ->assertRedirect();

    $run = \App\Models\PayrollRun::query()->findOrFail($runId);
    expect($run->status->value)->toBe('processed');

    $this->actingAs($user)
        ->post("/payroll/{$runId}/finalize")
        ->assertRedirect();

    $run->refresh();
    expect($run->status->value)->toBe('finalised');

    $this->actingAs($user)
        ->from('/payroll')
        ->post("/payroll/{$runId}/reopen", [
            'password' => 'wrong-pass',
        ])
        ->assertRedirect('/payroll')
        ->assertSessionHasErrors('password');

    $this->actingAs($user)
        ->post("/payroll/{$runId}/reopen", [
            'password' => 'secret-123',
            'reason' => 'Fix corrections',
        ])
        ->assertRedirect();

    $run->refresh();
    expect($run->status->value)->toBe('draft');
});

it('deletes draft payroll and blocks deleting processed runs', function () {
    $user = payrollTestUser();

    $this->actingAs($user)->post('/payroll', [
        'period_source' => 'custom',
        'from' => '2026-04-01',
        'to' => '2026-04-30',
    ]);

    $run = \App\Models\PayrollRun::query()->firstOrFail();

    $this->actingAs($user)
        ->delete("/payroll/{$run->id}")
        ->assertRedirect('/payroll')
        ->assertSessionHas('success');

    expect(\App\Models\PayrollRun::query()->whereKey($run->id)->exists())->toBeFalse();

    $this->actingAs($user)->post('/payroll', [
        'period_source' => 'custom',
        'from' => '2026-04-01',
        'to' => '2026-04-30',
    ]);

    $run = \App\Models\PayrollRun::query()->firstOrFail();
    $this->actingAs($user)->post("/payroll/{$run->id}/process");

    $this->actingAs($user)
        ->from('/payroll')
        ->delete("/payroll/{$run->id}")
        ->assertRedirect('/payroll')
        ->assertSessionHasErrors('run');

    expect(\App\Models\PayrollRun::query()->whereKey($run->id)->exists())->toBeTrue();
});

it('adds titled adjustments and blocks edits while finalised', function () {
    $user = payrollTestUser();

    $this->actingAs($user)->post('/payroll', [
        'period_source' => 'custom',
        'from' => '2026-05-01',
        'to' => '2026-05-31',
    ]);
    $run = \App\Models\PayrollRun::query()->firstOrFail();
    $employeeId = Employee::query()->value('id');

    $this->actingAs($user)
        ->post("/payroll/{$run->id}/employees/{$employeeId}/adjustments", [
            'type' => 'addition',
            'title' => 'Overtime bonus',
            'amount' => 150,
            'remarks' => 'Weekend cover',
        ])
        ->assertRedirect("/payroll/{$run->id}/employees/{$employeeId}/adjustments")
        ->assertSessionHas('success');

    $adjustment = \App\Models\PayrollRunAdjustment::query()->firstOrFail();
    expect($adjustment->title)->toBe('Overtime bonus');
    expect((float) $adjustment->amount)->toBe(150.0);

    $item = \App\Models\PayrollRunItem::query()->where('employee_id', $employeeId)->firstOrFail();
    expect((float) $item->manual_additions)->toBe(150.0);

    $this->actingAs($user)->get("/payroll/{$run->id}/employees/{$employeeId}/attendance")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Payroll/EmployeeAttendance'));

    $this->actingAs($user)->post("/payroll/{$run->id}/process");
    $this->actingAs($user)->post("/payroll/{$run->id}/finalize");

    $this->actingAs($user)
        ->from("/payroll/{$run->id}/employees/{$employeeId}/adjustments")
        ->post("/payroll/{$run->id}/employees/{$employeeId}/adjustments", [
            'type' => 'deduction',
            'title' => 'Late penalty',
            'amount' => 25,
        ])
        ->assertRedirect("/payroll/{$run->id}/employees/{$employeeId}/adjustments")
        ->assertSessionHasErrors('run');
});
