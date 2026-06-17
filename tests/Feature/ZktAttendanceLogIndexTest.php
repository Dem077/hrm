<?php

use App\Enums\AttendancePunchSource;
use App\Models\Employee;
use App\Models\User;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use App\Services\Attendance\ManualAttendancePunchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('zkt-attendance-logs.view');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('zkt-attendance-logs.view');

    $this->device = ZktDevice::query()->create([
        'name' => 'Main Gate',
        'ip_address' => '192.168.1.10',
        'is_active' => true,
        'connection_status' => 'connected',
    ]);

    $this->employee = Employee::query()->create([
        'staff_id' => 'EMP-LOG',
        'name' => 'Log Worker',
        'national_id' => 'NID-LOG',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'is_active' => true,
    ]);
});

it('excludes manual attendance sheet punches from punch logs', function () {
    app(ManualAttendancePunchService::class)->create(
        $this->employee,
        Carbon::parse('2026-06-10 09:00:00'),
        0,
        'Forgot to punch.',
        $this->user,
    );

    $response = $this->actingAs($this->user)->get('/zkt-attendance-logs');

    $response->assertOk();
    expect($response->original->getData()['page']['props']['logs']['data'])->toBeEmpty();
});

it('includes soft removed device punches in punch logs', function () {
    $log = ZktAttendanceLog::query()->create([
        'zkt_device_id' => $this->device->id,
        'device_uid' => 1,
        'device_user_id' => $this->employee->staff_id,
        'punch_state' => 0,
        'punch_type' => null,
        'punched_at' => Carbon::parse('2026-06-10 09:00:00'),
        'source' => AttendancePunchSource::Device,
    ]);

    app(ManualAttendancePunchService::class)->removeMany(
        collect([$log]),
        'Duplicate punch.',
        $this->user,
    );

    $response = $this->actingAs($this->user)->get('/zkt-attendance-logs');

    $response->assertOk();

    $logs = $response->original->getData()['page']['props']['logs']['data'];

    expect($logs)->toHaveCount(1)
        ->and($logs[0]['is_removed'])->toBeTrue()
        ->and($logs[0]['removal_reason'])->toBe('Duplicate punch.');
});

it('shows active device punches in punch logs', function () {
    ZktAttendanceLog::query()->create([
        'zkt_device_id' => $this->device->id,
        'device_uid' => 1,
        'device_user_id' => $this->employee->staff_id,
        'punch_state' => 0,
        'punch_type' => null,
        'punched_at' => Carbon::parse('2026-06-10 09:00:00'),
        'source' => AttendancePunchSource::Device,
    ]);

    $response = $this->actingAs($this->user)->get('/zkt-attendance-logs');

    $response->assertOk();

    $logs = $response->original->getData()['page']['props']['logs']['data'];

    expect($logs)->toHaveCount(1)
        ->and($logs[0]['is_removed'])->toBeFalse()
        ->and($logs[0]['source'])->toBe('device');
});
