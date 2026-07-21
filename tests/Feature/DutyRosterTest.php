<?php

use App\Enums\DutyType;
use App\Enums\StructureGroupCode;
use App\Models\AttendanceDutyPolicy;
use App\Models\DutyRoster;
use App\Models\DutyShiftTemplate;
use App\Models\Employee;
use App\Models\StructureGrade;
use App\Models\StructureGroup;
use App\Models\StructureLevel;
use App\Models\StructureNode;
use App\Models\User;
use App\Services\Attendance\AttendanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'duty-rosters.view',
        'duty-rosters.view-all',
        'duty-rosters.create',
        'duty-rosters.update',
        'duty-rosters.delete',
    ] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'duty-rosters.view',
        'duty-rosters.view-all',
        'duty-rosters.create',
        'duty-rosters.update',
        'duty-rosters.delete',
    ]);
});

it('shows duty roster page', function () {
    $this->actingAs($this->user)
        ->get('/duty-rosters')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('DutyRosters/Index'));
});

it('creates a duty roster entry for shift employees', function () {
    $employee = Employee::query()->create([
        'staff_id' => 'SHIFT-01',
        'name' => 'Shift Worker',
        'national_id' => 'NID-SHIFT-01',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'duty_type' => DutyType::Shift,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post('/duty-rosters', [
        'employee_id' => $employee->id,
        'duty_date' => '2026-06-10',
        'duty_start_time' => '14:00',
        'duty_end_time' => '22:00',
        'grace_minutes' => 10,
        'notes' => 'Night shift',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(DutyRoster::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

it('uses roster duty times on attendance sheet for shift employees', function () {
    $employee = Employee::query()->create([
        'staff_id' => 'SHIFT-02',
        'name' => 'Roster Worker',
        'national_id' => 'NID-SHIFT-02',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'duty_type' => DutyType::Shift,
        'is_active' => true,
    ]);

    DutyRoster::query()->create([
        'employee_id' => $employee->id,
        'duty_date' => '2026-06-10',
        'duty_start_time' => '14:00:00',
        'duty_end_time' => '22:00:00',
        'grace_minutes' => 10,
    ]);

    $date = Carbon::parse('2026-06-10', config('app.timezone', 'UTC'));
    $result = app(AttendanceSheetService::class)->build($date, $date, employeeId: $employee->id);

    expect($result['rows'][0]['duty_start_time'])->toBe('14:00');
    expect($result['rows'][0]['duty_end_time'])->toBe('22:00');
});

it('uses roster duty times instead of temporary duty policy for shift employees', function () {
    AttendanceDutyPolicy::ensureDefault();

    AttendanceDutyPolicy::query()->create([
        'effective_from' => '2026-06-01',
        'effective_until' => '2026-06-30',
        'name' => 'Ramadan hours',
        'duty_start_time' => '08:00:00',
        'duty_end_time' => '16:00:00',
        'grace_minutes' => 10,
        'saturday_duty_start_time' => '08:00:00',
        'saturday_duty_end_time' => '14:00:00',
        'saturday_grace_minutes' => 10,
    ]);

    $employee = Employee::query()->create([
        'staff_id' => 'SHIFT-04',
        'name' => 'Policy Override Worker',
        'national_id' => 'NID-SHIFT-04',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'duty_type' => DutyType::Shift,
        'is_active' => true,
    ]);

    DutyRoster::query()->create([
        'employee_id' => $employee->id,
        'duty_date' => '2026-06-10',
        'duty_start_time' => '14:00:00',
        'duty_end_time' => '22:00:00',
        'grace_minutes' => 10,
    ]);

    $date = Carbon::parse('2026-06-10', config('app.timezone', 'UTC'));
    $result = app(AttendanceSheetService::class)->build($date, $date, employeeId: $employee->id);

    expect($result['rows'][0]['duty_start_time'])->toBe('14:00');
    expect($result['rows'][0]['duty_end_time'])->toBe('22:00');
});

it('creates a fixed duty shift template', function () {
    $response = $this->actingAs($this->user)->post('/duty-shift-templates', [
        'name' => 'Night Shift',
        'duty_start_time' => '14:00',
        'duty_end_time' => '22:00',
        'grace_minutes' => 10,
        'notes' => 'Evening shift',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(DutyShiftTemplate::query()->where('name', 'Night Shift')->exists())->toBeTrue();
});

it('bulk assigns duty to multiple employees using a fixed duty template', function () {
    $template = DutyShiftTemplate::query()->create([
        'name' => 'Morning Shift',
        'duty_start_time' => '06:00:00',
        'duty_end_time' => '14:00:00',
        'grace_minutes' => 10,
        'is_active' => true,
    ]);

    $employees = collect(['SHIFT-A', 'SHIFT-B'])->map(fn (string $staffId) => Employee::query()->create([
        'staff_id' => $staffId,
        'name' => "Worker {$staffId}",
        'national_id' => "NID-{$staffId}",
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'duty_type' => DutyType::Shift,
        'is_active' => true,
    ]));

    $response = $this->actingAs($this->user)->post('/duty-rosters/bulk-assign', [
        'employee_ids' => $employees->pluck('id')->all(),
        'date_mode' => 'range',
        'from_date' => '2026-06-10',
        'to_date' => '2026-06-12',
        'duty_shift_template_id' => $template->id,
        'skip_weekends' => true,
        'notes' => 'Weekday coverage',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(DutyRoster::query()->count())->toBe(4);

    $firstEntry = DutyRoster::query()
        ->where('employee_id', $employees->first()->id)
        ->whereDate('duty_date', '2026-06-10')
        ->first();

    expect($firstEntry?->duty_start_time)->toBe('06:00:00')
        ->and($firstEntry?->duty_end_time)->toBe('14:00:00')
        ->and($firstEntry?->notes)->toBe('Weekday coverage');
});

it('bulk assigns custom duty times to multiple employees', function () {
    $employees = collect(['SHIFT-C', 'SHIFT-D'])->map(fn (string $staffId) => Employee::query()->create([
        'staff_id' => $staffId,
        'name' => "Worker {$staffId}",
        'national_id' => "NID-{$staffId}",
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'duty_type' => DutyType::Shift,
        'is_active' => true,
    ]));

    $response = $this->actingAs($this->user)->post('/duty-rosters/bulk-assign', [
        'employee_ids' => $employees->pluck('id')->all(),
        'date_mode' => 'range',
        'from_date' => '2026-06-10',
        'to_date' => '2026-06-10',
        'duty_start_time' => '18:00',
        'duty_end_time' => '23:00',
        'grace_minutes' => 5,
    ]);

    $response->assertRedirect();

    expect(DutyRoster::query()->count())->toBe(2);

    $entry = DutyRoster::query()->where('employee_id', $employees->first()->id)->first();

    expect($entry?->duty_start_time)->toBe('18:00:00')
        ->and($entry?->duty_end_time)->toBe('23:00:00');
});

it('bulk assigns duty using specific dates', function () {
    $employee = Employee::query()->create([
        'staff_id' => 'SHIFT-MULTI',
        'name' => 'Multi Date Worker',
        'national_id' => 'NID-SHIFT-MULTI',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'duty_type' => DutyType::Shift,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post('/duty-rosters/bulk-assign', [
        'employee_ids' => [$employee->id],
        'date_mode' => 'specific',
        'duty_dates' => ['2026-06-10', '2026-06-12', '2026-06-15'],
        'duty_start_time' => '10:00',
        'duty_end_time' => '18:00',
        'grace_minutes' => 15,
    ]);

    $response->assertRedirect();

    expect(DutyRoster::query()->where('employee_id', $employee->id)->count())->toBe(3);
});

it('limits roster creation to the auth user department without view-all permission', function () {
    Permission::findOrCreate('duty-rosters.view-all');

    $group = StructureGroup::query()->where('code', StructureGroupCode::Department)->firstOrFail();
    $operations = StructureNode::query()->create([
        'structure_group_id' => $group->id,
        'name' => 'Operations',
        'is_active' => true,
    ]);
    $finance = StructureNode::query()->create([
        'structure_group_id' => $group->id,
        'name' => 'Finance',
        'is_active' => true,
    ]);

    $opsLevel = StructureLevel::query()->create([
        'structure_node_id' => $operations->id,
        'level_number' => 1,
        'reference_title' => 'Staff',
    ]);
    $finLevel = StructureLevel::query()->create([
        'structure_node_id' => $finance->id,
        'level_number' => 1,
        'reference_title' => 'Staff',
    ]);
    $opsGrade = StructureGrade::query()->create([
        'structure_level_id' => $opsLevel->id,
        'grade' => 'A',
        'title' => 'Ops Role',
        'is_active' => true,
    ]);
    $finGrade = StructureGrade::query()->create([
        'structure_level_id' => $finLevel->id,
        'grade' => 'A',
        'title' => 'Finance Role',
        'is_active' => true,
    ]);

    $manager = Employee::query()->create([
        'staff_id' => 'MGR-01',
        'name' => 'Ops Manager',
        'national_id' => 'NID-MGR-01',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'grade_id' => $opsGrade->id,
        'duty_type' => DutyType::Normal,
        'is_active' => true,
    ]);

    $departmentStaff = Employee::query()->create([
        'staff_id' => 'SHIFT-OPS',
        'name' => 'Ops Shift Worker',
        'national_id' => 'NID-SHIFT-OPS',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'grade_id' => $opsGrade->id,
        'duty_type' => DutyType::Shift,
        'is_active' => true,
    ]);

    $otherDepartmentStaff = Employee::query()->create([
        'staff_id' => 'SHIFT-FIN',
        'name' => 'Finance Shift Worker',
        'national_id' => 'NID-SHIFT-FIN',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'grade_id' => $finGrade->id,
        'duty_type' => DutyType::Shift,
        'is_active' => true,
    ]);

    $departmentUser = User::factory()->create();
    $departmentUser->givePermissionTo([
        'duty-rosters.view',
        'duty-rosters.create',
        'duty-rosters.update',
        'duty-rosters.delete',
    ]);
    $manager->update(['user_id' => $departmentUser->id]);

    $this->actingAs($departmentUser)
        ->get('/duty-rosters')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewAll', false)
            ->has('allShiftEmployees', 1)
            ->where('allShiftEmployees.0.id', $departmentStaff->id));

    $this->actingAs($departmentUser)
        ->post('/duty-rosters', [
            'employee_id' => $otherDepartmentStaff->id,
            'duty_date' => '2026-06-10',
            'duty_start_time' => '14:00',
            'duty_end_time' => '22:00',
            'grace_minutes' => 10,
        ])
        ->assertSessionHasErrors('employee_id');

    $this->actingAs($departmentUser)
        ->post('/duty-rosters', [
            'employee_id' => $departmentStaff->id,
            'duty_date' => '2026-06-10',
            'duty_start_time' => '14:00',
            'duty_end_time' => '22:00',
            'grace_minutes' => 10,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('marks shift employees without roster as off roster', function () {
    $employee = Employee::query()->create([
        'staff_id' => 'SHIFT-03',
        'name' => 'Unscheduled Worker',
        'national_id' => 'NID-SHIFT-03',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'duty_type' => DutyType::Shift,
        'is_active' => true,
    ]);

    $date = Carbon::parse('2026-06-11', config('app.timezone', 'UTC'));
    $result = app(AttendanceSheetService::class)->build($date, $date, employeeId: $employee->id);

    expect($result['rows'][0]['status_label'])->toBe('Off roster');
});
