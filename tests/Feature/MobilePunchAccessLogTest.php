<?php

use App\Enums\AttendancePunchSource;
use App\Enums\ZktConnectionMode;
use App\Enums\ZktMachineType;
use App\Models\Employee;
use App\Models\RemoteDoorOpenLog;
use App\Models\RemoteDoorSite;
use App\Models\SelfPunchSite;
use App\Models\User;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (PermissionRegistry::all() as $permission) {
        Permission::findOrCreate($permission);
    }

    $role = Role::findOrCreate('Mobile Punch Log Viewer');
    $role->syncPermissions(['mobile-punch-logs.view']);

    $this->user = User::factory()->create();
    $this->user->assignRole($role);

    $this->employee = Employee::query()->create([
        'staff_id' => 'MP100',
        'name' => 'Mobile Puncher',
        'national_id' => 'NID-MP',
        'joined_date' => '2024-01-01',
        'gender' => 'female',
        'is_active' => true,
        'user_id' => $this->user->id,
        'bank_name' => 'Test Bank',
        'account_name' => 'Mobile Puncher',
        'account_no' => '123',
    ]);

    $this->punchSite = SelfPunchSite::query()->create([
        'name' => 'HQ',
        'code' => 'HQ1',
        'latitude' => 4.1755,
        'longitude' => 73.5093,
        'radius_meters' => 100,
        'max_accuracy_meters' => 100,
        'is_active' => true,
    ]);

    ZktDevice::selfPunchDevice();

    $this->punchLog = ZktAttendanceLog::query()->create([
        'zkt_device_id' => ZktDevice::selfPunchDevice()->id,
        'device_uid' => 1,
        'device_user_id' => 'MP100',
        'punch_state' => 0,
        'punched_at' => now(),
        'source' => AttendancePunchSource::SelfApp,
        'self_punch_site_id' => $this->punchSite->id,
        'latitude' => 4.1755,
        'longitude' => 73.5093,
        'accuracy_meters' => 20,
        'client_ip' => '203.0.113.10',
        'request_ip' => '192.168.1.50',
        'client_device_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    ]);
});

it('requires permission to view mobile punch access logs', function () {
    $this->actingAs(User::factory()->create())
        ->get('/mobile-punch-logs')
        ->assertForbidden();
});

it('lists mobile punch audit logs with device and network details', function () {
    $response = $this->actingAs($this->user)->get('/mobile-punch-logs');

    $response->assertOk();

    $logs = $response->original->getData()['page']['props']['punchLogs']['data'];

    expect($logs)->toHaveCount(1)
        ->and($logs[0]['client_device_id'])->toBe('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')
        ->and($logs[0]['request_ip'])->toBe('192.168.1.50')
        ->and($logs[0]['site']['code'])->toBe('HQ1');
});

it('excludes non-mobile punch logs from the audit page', function () {
    ZktAttendanceLog::query()->create([
        'zkt_device_id' => ZktDevice::selfPunchDevice()->id,
        'device_uid' => 2,
        'device_user_id' => 'MP100',
        'punch_state' => 1,
        'punched_at' => now()->subMinute(),
        'source' => AttendancePunchSource::Device,
    ]);

    $response = $this->actingAs($this->user)->get('/mobile-punch-logs');

    expect($response->original->getData()['page']['props']['punchLogs']['data'])->toHaveCount(1);
});

it('filters mobile punch logs by device code', function () {
    $this->actingAs($this->user)
        ->get('/mobile-punch-logs?client_device_id=bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')
        ->assertOk();

    $response = $this->actingAs($this->user)->get('/mobile-punch-logs?client_device_id=aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');

    expect($response->original->getData()['page']['props']['punchLogs']['data'])->toHaveCount(1);
});

it('lists door open audit logs on the door tab', function () {
    $device = ZktDevice::query()->create([
        'name' => 'Front Door',
        'ip_address' => '0.0.0.0',
        'connection_mode' => ZktConnectionMode::AdmsPush,
        'serial_number' => 'DOOR123456',
        'machine_type' => ZktMachineType::Access,
        'is_active' => true,
    ]);

    $doorSite = RemoteDoorSite::query()->create([
        'zkt_device_id' => $device->id,
        'name' => 'Lobby',
        'code' => 'LB1',
        'latitude' => 4.17,
        'longitude' => 73.5,
        'radius_meters' => 100,
        'max_accuracy_meters' => 100,
        'is_active' => true,
    ]);

    RemoteDoorOpenLog::query()->create([
        'remote_door_site_id' => $doorSite->id,
        'zkt_device_id' => $device->id,
        'user_id' => $this->user->id,
        'employee_id' => $this->employee->id,
        'latitude' => 4.17,
        'longitude' => 73.5,
        'accuracy_meters' => 15,
        'client_ip' => '203.0.113.20',
        'status' => 'queued',
        'result_message' => 'Unlock command queued.',
        'opened_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get('/mobile-punch-logs?tab=doors');

    $logs = $response->original->getData()['page']['props']['doorLogs']['data'];

    expect($logs)->toHaveCount(1)
        ->and($logs[0]['site']['code'])->toBe('LB1')
        ->and($logs[0]['client_ip'])->toBe('203.0.113.20')
        ->and($logs[0]['device']['name'])->toBe('Front Door');
});
