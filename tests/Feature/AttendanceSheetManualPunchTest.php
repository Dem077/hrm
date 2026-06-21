<?php

use App\Enums\AttendancePunchSource;
use App\Enums\ZktMachineType;
use App\Models\Employee;
use App\Models\User;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use App\Services\Attendance\AttendanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['attendance-sheet.view', 'attendance-sheet.view-all', 'attendance-sheet.add-punch', 'attendance-sheet.remove-punch'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'attendance-sheet.view',
        'attendance-sheet.view-all',
        'attendance-sheet.add-punch',
        'attendance-sheet.remove-punch',
    ]);

    $this->employee = Employee::query()->create([
        'staff_id' => 'EMP-PUNCH',
        'name' => 'Punch Worker',
        'national_id' => 'NID-PUNCH',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'is_active' => true,
    ]);
});

it('adds a manual punch from the attendance sheet with a required reason', function () {
    $response = $this->actingAs($this->user)->post('/attendance-sheet/manual-punches', [
        'employee_id' => $this->employee->id,
        'duty_date' => '2026-06-10',
        'punched_at' => '09:15',
        'punch_state' => 0,
        'reason' => 'Forgot to punch at the machine.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $log = ZktAttendanceLog::query()->where('device_user_id', $this->employee->staff_id)->first();

    expect($log)->not->toBeNull()
        ->and($log->source)->toBe(AttendancePunchSource::AttendanceSheet)
        ->and($log->manual_reason)->toBe('Forgot to punch at the machine.')
        ->and($log->added_by_user_id)->toBe($this->user->id);
});

it('requires a reason when adding a manual punch', function () {
    $this->actingAs($this->user)
        ->post('/attendance-sheet/manual-punches', [
            'employee_id' => $this->employee->id,
            'duty_date' => '2026-06-10',
            'punched_at' => '09:15',
            'punch_state' => 0,
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');
});

it('shows manual punches on the attendance sheet', function () {
    $this->actingAs($this->user)->post('/attendance-sheet/manual-punches', [
        'employee_id' => $this->employee->id,
        'duty_date' => '2026-06-10',
        'punched_at' => '09:10',
        'punch_state' => 0,
        'reason' => 'Late machine sync.',
    ]);

    $date = Carbon::parse('2026-06-10', config('app.timezone', 'UTC'));
    $result = app(AttendanceSheetService::class)->build($date, $date, employeeId: $this->employee->id);

    expect($result['rows'][0]['check_in_is_manual'])->toBeTrue()
        ->and($result['rows'][0]['check_in_manual_reason'])->toBe('Late machine sync.')
        ->and($result['rows'][0]['has_punch_edits'])->toBeTrue()
        ->and($result['rows'][0]['punch_edits'])->toHaveCount(1)
        ->and($result['rows'][0]['punch_edits'][0]['action'])->toBe('added')
        ->and($result['rows'][0]['punch_edits'][0]['reason'])->toBe('Late machine sync.')
        ->and($result['rows'][0]['punch_edits'][0]['acted_by_name'])->toBe($this->user->name);
});

it('soft removes a punch from the attendance sheet with a required reason', function () {
    $this->actingAs($this->user)->post('/attendance-sheet/manual-punches', [
        'employee_id' => $this->employee->id,
        'duty_date' => '2026-06-10',
        'punched_at' => '09:10',
        'punch_state' => 0,
        'reason' => 'Late machine sync.',
    ]);

    $log = ZktAttendanceLog::query()->where('device_user_id', $this->employee->staff_id)->first();

    $response = $this->actingAs($this->user)->delete('/attendance-sheet/manual-punches', [
        'punch_log_ids' => [$log->id],
        'reason' => 'Entered by mistake.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(ZktAttendanceLog::query()->whereKey($log->id)->exists())->toBeFalse();

    $trashed = ZktAttendanceLog::withTrashed()->find($log->id);

    expect($trashed)->not->toBeNull()
        ->and($trashed->removal_reason)->toBe('Entered by mistake.')
        ->and($trashed->removed_by_user_id)->toBe($this->user->id);

    $date = Carbon::parse('2026-06-10', config('app.timezone', 'UTC'));
    $result = app(AttendanceSheetService::class)->build($date, $date, employeeId: $this->employee->id);

    expect($result['rows'][0]['check_in'])->toBeNull()
        ->and($result['rows'][0]['has_removable_punch'])->toBeFalse()
        ->and($result['rows'][0]['has_punch_edits'])->toBeTrue()
        ->and($result['rows'][0]['punch_edits'])->toHaveCount(2)
        ->and(collect($result['rows'][0]['punch_edits'])->pluck('action')->all())->toBe(['added', 'removed'])
        ->and(collect($result['rows'][0]['punch_edits'])->pluck('acted_by_name')->all())->toBe([$this->user->name, $this->user->name]);
});

it('requires a reason when removing a punch', function () {
    $this->actingAs($this->user)->post('/attendance-sheet/manual-punches', [
        'employee_id' => $this->employee->id,
        'duty_date' => '2026-06-10',
        'punched_at' => '09:10',
        'punch_state' => 0,
        'reason' => 'Late machine sync.',
    ]);

    $log = ZktAttendanceLog::query()->where('device_user_id', $this->employee->staff_id)->first();

    $this->actingAs($this->user)
        ->delete('/attendance-sheet/manual-punches', [
            'punch_log_ids' => [$log->id],
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');
});

it('excludes access machine punches from the attendance sheet', function () {
    $attendanceDevice = ZktDevice::query()->create([
        'name' => 'Office Attendance',
        'ip_address' => '192.168.1.10',
        'machine_type' => ZktMachineType::Attendance,
        'is_active' => true,
        'auto_sync' => false,
    ]);

    $accessDevice = ZktDevice::query()->create([
        'name' => 'Main Door',
        'ip_address' => '192.168.1.11',
        'machine_type' => ZktMachineType::Access,
        'is_active' => true,
        'auto_sync' => false,
    ]);

    $date = Carbon::parse('2026-06-10 09:00:00', config('app.timezone', 'UTC'));

    ZktAttendanceLog::query()->create([
        'zkt_device_id' => $accessDevice->id,
        'device_uid' => 1,
        'device_user_id' => $this->employee->staff_id,
        'punch_state' => 0,
        'punched_at' => $date->copy()->setTime(9, 0),
        'source' => AttendancePunchSource::Device,
    ]);

    ZktAttendanceLog::query()->create([
        'zkt_device_id' => $attendanceDevice->id,
        'device_uid' => 2,
        'device_user_id' => $this->employee->staff_id,
        'punch_state' => 0,
        'punched_at' => $date->copy()->setTime(9, 5),
        'source' => AttendancePunchSource::Device,
    ]);

    $result = app(AttendanceSheetService::class)->build(
        $date->copy()->startOfDay(),
        $date->copy()->startOfDay(),
        employeeId: $this->employee->id,
    );

    expect($result['rows'][0]['check_in'])->not->toBeNull()
        ->and($result['rows'][0]['check_in'])->toContain('09:05');
});
