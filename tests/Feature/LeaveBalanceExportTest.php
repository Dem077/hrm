<?php

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Leave\LeaveBalanceExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('leave-balances.view');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('leave-balances.view');
});

it('exports leave balances as an excel file', function () {
    $employee = Employee::query()->create([
        'staff_id' => 'EMP-100',
        'name' => 'Export Test',
        'national_id' => 'NID-100',
        'joined_date' => '2024-03-15',
        'gender' => 'male',
        'is_active' => true,
    ]);

    LeaveType::query()->create([
        'name' => 'Annual Leave',
        'annual_limit' => 14,
    ]);

    $response = $this->actingAs($this->user)->get('/leave-balances/export?employee_id='.$employee->id);

    $response->assertOk();
    $response->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );
    expect($response->headers->get('content-disposition'))->toContain('leave-balances-');
    expect($response->streamedContent())->not->toBe('');
});

it('requires an employee before exporting leave balances', function () {
    $this->actingAs($this->user)
        ->get('/leave-balances/export')
        ->assertStatus(422);
});

it('forbids leave balance export without permission', function () {
    $employee = Employee::query()->create([
        'staff_id' => 'EMP-101',
        'name' => 'No Access',
        'national_id' => 'NID-101',
        'joined_date' => '2024-03-15',
        'gender' => 'male',
        'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/leave-balances/export?employee_id='.$employee->id)
        ->assertForbidden();
});

it('exports all employees used leave for their current leave year', function () {
    Employee::query()->create([
        'staff_id' => 'EMP-200',
        'name' => 'All Export One',
        'national_id' => 'NID-200',
        'joined_date' => '2024-03-15',
        'gender' => 'male',
        'is_active' => true,
    ]);

    Employee::query()->create([
        'staff_id' => 'EMP-201',
        'name' => 'All Export Two',
        'national_id' => 'NID-201',
        'joined_date' => '2024-06-01',
        'gender' => 'female',
        'is_active' => true,
    ]);

    LeaveType::query()->create([
        'name' => 'Annual Leave',
        'annual_limit' => 14,
    ]);

    $response = $this->actingAs($this->user)->get('/leave-balances/export-all');

    $response->assertOk();
    $response->assertHeader(
        'content-type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );
    expect($response->headers->get('content-disposition'))->toContain('leave-balances-all-employees-');
    expect($response->streamedContent())->not->toBe('');
});

it('uses each employee joining date for leave year in the all employees export', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-08', config('app.timezone', 'UTC')));

    Employee::query()->create([
        'staff_id' => 'EMP-300',
        'name' => 'Alpha Employee',
        'national_id' => 'NID-300',
        'joined_date' => '2024-03-15',
        'gender' => 'male',
        'is_active' => true,
    ]);

    Employee::query()->create([
        'staff_id' => 'EMP-301',
        'name' => 'Beta Employee',
        'national_id' => 'NID-301',
        'joined_date' => '2024-06-01',
        'gender' => 'female',
        'is_active' => true,
    ]);

    LeaveType::query()->create([
        'name' => 'Annual Leave',
        'annual_limit' => 14,
    ]);

    $rows = app(LeaveBalanceExportService::class)->buildAllEmployeesUsedRows();

    expect($rows[0][3])->toBe('15/03/2026 – 14/03/2027')
        ->and($rows[1][3])->toBe('01/06/2026 – 31/05/2027');
});
