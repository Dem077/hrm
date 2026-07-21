<?php

use App\Enums\ZktConnectionMode;
use App\Enums\ZktMachineType;
use App\Models\Employee;
use App\Models\RemoteDoorOpenLog;
use App\Models\RemoteDoorSite;
use App\Models\User;
use App\Models\ZktAdmsCommand;
use App\Models\ZktDevice;
use App\Services\Zkt\ZktDeviceClient;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (PermissionRegistry::all() as $permission) {
        Permission::findOrCreate($permission);
    }

    $role = Role::findOrCreate('Remote Door Tester');
    $role->syncPermissions(['self-punch.use', 'self-punch-sites.view', 'self-punch-sites.create', 'self-punch-sites.update', 'self-punch-sites.delete']);

    $this->user = User::factory()->create();
    $this->user->assignRole($role);

    $this->employee = Employee::query()->create([
        'staff_id' => 'RD100',
        'name' => 'Door Opener',
        'national_id' => 'NID-RD',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'is_active' => true,
        'user_id' => $this->user->id,
        'bank_name' => 'Test Bank',
        'account_name' => 'Door Opener',
        'account_no' => '456',
    ]);

    $this->device = ZktDevice::query()->create([
        'name' => 'Main Door',
        'ip_address' => '0.0.0.0',
        'connection_mode' => ZktConnectionMode::AdmsPush,
        'serial_number' => 'DOOR123456',
        'machine_type' => ZktMachineType::Access,
        'is_active' => true,
    ]);

    $this->site = RemoteDoorSite::query()->create([
        'zkt_device_id' => $this->device->id,
        'name' => 'Front Entrance',
        'latitude' => 4.1755,
        'longitude' => 73.5093,
        'radius_meters' => 100,
        'max_accuracy_meters' => 250,
        'require_public_ip' => false,
        'allowed_public_ips' => [],
        'is_active' => true,
    ]);
    $this->site->employees()->attach($this->employee->id);
});

it('queues a door unlock when employee is at an assigned site', function () {
    $response = $this->actingAs($this->user)->post('/self-punch/open-door', [
        'remote_door_site_id' => $this->site->id,
        'latitude' => 4.1755,
        'longitude' => 73.5093,
        'accuracy_meters' => 20,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(RemoteDoorOpenLog::query()->count())->toBe(1)
        ->and(ZktAdmsCommand::query()->count())->toBe(1)
        ->and(ZktAdmsCommand::query()->first()->payload)->toBe('AC_UNLOCK');
});

it('opens the door immediately for tcp access machines', function () {
    $tcpDevice = ZktDevice::query()->create([
        'name' => 'TCP Door',
        'ip_address' => '192.168.1.55',
        'port' => 4370,
        'protocol' => 'tcp',
        'connection_mode' => ZktConnectionMode::TcpPull,
        'machine_type' => ZktMachineType::Access,
        'is_active' => true,
    ]);

    $site = RemoteDoorSite::query()->create([
        'zkt_device_id' => $tcpDevice->id,
        'name' => 'Side Door',
        'latitude' => 4.1755,
        'longitude' => 73.5093,
        'radius_meters' => 100,
        'max_accuracy_meters' => 250,
        'require_public_ip' => false,
        'allowed_public_ips' => [],
        'is_active' => true,
    ]);
    $site->employees()->attach($this->employee->id);

    $this->mock(ZktDeviceClient::class, function ($mock) use ($tcpDevice): void {
        $mock->shouldReceive('unlockDoor')
            ->once()
            ->withArgs(fn (ZktDevice $device) => $device->id === $tcpDevice->id);
    });

    $response = $this->actingAs($this->user)->post('/self-punch/open-door', [
        'remote_door_site_id' => $site->id,
        'latitude' => 4.1755,
        'longitude' => 73.5093,
        'accuracy_meters' => 20,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(RemoteDoorOpenLog::query()->count())->toBe(1)
        ->and(RemoteDoorOpenLog::query()->first()->status)->toBe('opened')
        ->and(ZktAdmsCommand::query()->count())->toBe(0);
});

it('rejects door open outside the geofence', function () {
    $response = $this->actingAs($this->user)->from('/self-punch')->post('/self-punch/open-door', [
        'remote_door_site_id' => $this->site->id,
        'latitude' => 4.2,
        'longitude' => 73.6,
        'accuracy_meters' => 20,
    ]);

    $response->assertRedirect('/self-punch');
    $response->assertSessionHasErrors('location');
    expect(RemoteDoorOpenLog::query()->count())->toBe(0);
});

it('creates a remote door site with assigned employees', function () {
    $response = $this->actingAs($this->user)->post('/remote-door-sites', [
        'zkt_device_id' => $this->device->id,
        'name' => 'Back Door',
        'latitude' => 4.18,
        'longitude' => 73.51,
        'radius_meters' => 150,
        'max_accuracy_meters' => 250,
        'require_public_ip' => false,
        'is_active' => true,
        'employee_ids' => [$this->employee->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $site = RemoteDoorSite::query()->where('name', 'Back Door')->firstOrFail();
    expect($site->zkt_device_id)->toBe($this->device->id)
        ->and($site->employees)->toHaveCount(1);
});
