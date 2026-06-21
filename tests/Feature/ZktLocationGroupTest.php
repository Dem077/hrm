<?php

use App\Enums\ZktDevicePrivilege;
use App\Enums\ZktDeviceUserSyncStatus;
use App\Models\Employee;
use App\Models\User;
use App\Models\ZktDevice;
use App\Models\ZktDeviceEmployeeSync;
use App\Models\ZktLocationGroup;
use App\Services\Zkt\ZktDeviceClient;
use App\Services\Zkt\ZktDeviceUserSyncService;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->permissions = [
        'zkt-location-groups.view',
        'zkt-location-groups.create',
        'zkt-location-groups.update',
        'zkt-location-groups.delete',
        'zkt-location-groups.sync-users',
        'zkt-devices.manage-users',
        'employees.view',
        'employees.update',
        'employees.create',
    ];

    foreach ($this->permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    foreach (PermissionRegistry::all() as $permission) {
        Permission::findOrCreate($permission);
    }

    $superAdmin = Role::findOrCreate(PermissionRegistry::superAdminRole());
    $superAdmin->syncPermissions(PermissionRegistry::all());

    $this->user = User::factory()->create();
    $this->user->assignRole($superAdmin);

    $this->device = ZktDevice::query()->create([
        'name' => 'Main Gate',
        'ip_address' => '192.168.1.50',
        'port' => 4370,
        'protocol' => 'tcp',
        'is_active' => true,
        'auto_sync' => false,
    ]);

    $this->employee = Employee::query()->create([
        'staff_id' => 'EMP001',
        'name' => 'Jane Doe',
        'national_id' => 'NID001',
        'joined_date' => '2024-01-01',
        'gender' => 'female',
        'is_active' => true,
    ]);
});

it('creates a location group with assigned machines', function () {
    $response = $this->actingAs($this->user)->post('/zkt-location-groups', [
        'name' => 'Head Office',
        'code' => 'HO',
        'description' => 'Office floor machines',
        'sort_order' => 1,
        'is_active' => true,
        'device_ids' => [$this->device->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $group = ZktLocationGroup::query()->where('code', 'HO')->firstOrFail();

    expect($group->devices)->toHaveCount(1)
        ->and($group->devices->first()->id)->toBe($this->device->id);
});

it('syncs an employee profile to devices in assigned location groups', function () {
    $group = ZktLocationGroup::query()->create([
        'name' => 'Head Office',
        'is_active' => true,
    ]);
    $group->devices()->attach($this->device->id);
    $group->employees()->attach($this->employee->id);

    $client = Mockery::mock(ZktDeviceClient::class);
    $client->shouldReceive('fetchUsers')
        ->once()
        ->andReturn([]);
    $client->shouldReceive('pushUser')
        ->once()
        ->with(
            Mockery::on(fn ($device) => $device->id === $this->device->id),
            1,
            'EMP001',
            'Jane Doe',
            0,
            0,
            '',
        )
        ->andReturn(['uid' => 1, 'user_id' => 'EMP001']);

    $service = new ZktDeviceUserSyncService($client);
    $results = $service->syncEmployee($this->employee->fresh(['zktLocationGroups', 'zktDeviceSyncs.device']));

    expect($results)->toHaveCount(1)
        ->and($results[0]['status'])->toBe('synced');

    $sync = ZktDeviceEmployeeSync::query()
        ->where('employee_id', $this->employee->id)
        ->where('zkt_device_id', $this->device->id)
        ->first();

    expect($sync)->not->toBeNull()
        ->and($sync->sync_status)->toBe(ZktDeviceUserSyncStatus::Synced)
        ->and($sync->device_uid)->toBe(1);
});

it('removes an employee from devices when location group access is removed', function () {
    $group = ZktLocationGroup::query()->create([
        'name' => 'Head Office',
        'is_active' => true,
    ]);
    $group->devices()->attach($this->device->id);
    $group->employees()->attach($this->employee->id);

    ZktDeviceEmployeeSync::query()->create([
        'employee_id' => $this->employee->id,
        'zkt_device_id' => $this->device->id,
        'device_uid' => 1,
        'sync_status' => ZktDeviceUserSyncStatus::Synced,
        'last_synced_at' => now(),
    ]);

    $group->employees()->detach($this->employee->id);

    $client = Mockery::mock(ZktDeviceClient::class);
    $client->shouldReceive('fetchUsers')
        ->once()
        ->andReturn([
            ['uid' => 1, 'user_id' => 'EMP001', 'name' => 'Jane Doe'],
        ]);
    $client->shouldReceive('removeUserByUid')
        ->once()
        ->with(
            Mockery::on(fn ($device) => $device->id === $this->device->id),
            1,
        );

    $service = new ZktDeviceUserSyncService($client);
    $results = $service->syncEmployee($this->employee->fresh(['zktLocationGroups', 'zktDeviceSyncs.device']));

    expect($results)->toHaveCount(1)
        ->and($results[0]['status'])->toBe('removed');

    expect(ZktDeviceEmployeeSync::query()->first()->sync_status)->toBe(ZktDeviceUserSyncStatus::Removed);
});

it('assigns location groups when updating an employee', function () {
    $group = ZktLocationGroup::query()->create([
        'name' => 'Warehouse',
        'is_active' => true,
    ]);
    $group->devices()->attach($this->device->id);

    $client = Mockery::mock(ZktDeviceClient::class);
    $client->shouldReceive('fetchUsers')->andReturn([]);
    $client->shouldReceive('pushUser')->andReturn(['uid' => 1, 'user_id' => 'EMP001']);
    $this->app->instance(ZktDeviceClient::class, $client);

    $response = $this->actingAs($this->user)->put("/employees/{$this->employee->id}", [
        'staff_id' => $this->employee->staff_id,
        'name' => $this->employee->name,
        'national_id' => $this->employee->national_id,
        'email' => 'jane@example.com',
        'mobile_number' => '',
        'joined_date' => '2024-01-01',
        'gender' => 'female',
        'department_id' => '',
        'designation_id' => '',
        'manager_id' => '',
        'is_active' => true,
        'works_saturday' => false,
        'duty_type' => 'normal',
        'uses_custom_duty_times' => false,
        'zkt_location_group_ids' => [$group->id],
    ]);

    $response->assertRedirect();

    expect($this->employee->fresh()->zktLocationGroups)->toHaveCount(1);
});

it('pulls card number password and privilege from a machine into an employee record', function () {
    $group = ZktLocationGroup::query()->create([
        'name' => 'Head Office',
        'is_active' => true,
    ]);
    $group->devices()->attach($this->device->id);
    $group->employees()->attach($this->employee->id);

    $client = Mockery::mock(ZktDeviceClient::class);
    $client->shouldReceive('fetchUsers')
        ->once()
        ->andReturn([
            'EMP001' => [
                'uid' => 8,
                'user_id' => 'EMP001',
                'name' => 'Jane Doe',
                'role' => 14,
                'password' => '1121',
                'card_no' => '0012345678 ',
            ],
        ]);

    $service = new ZktDeviceUserSyncService($client);
    $result = $service->pullEmployeeCredentialsFromDevices($this->employee);

    $employee = $this->employee->fresh();

    expect($result['status'])->toBe('updated')
        ->and($employee->device_card_number)->toBe('0012345678')
        ->and($employee->device_password)->toBe('1121')
        ->and($employee->device_privilege)->toBe(ZktDevicePrivilege::Administrator);
});

it('pulls matching employee credentials from a device in bulk', function () {
    $client = Mockery::mock(ZktDeviceClient::class);
    $client->shouldReceive('fetchUsers')
        ->once()
        ->andReturn([
            'EMP001' => [
                'uid' => 8,
                'user_id' => 'EMP001',
                'name' => 'Jane Doe',
                'role' => 4,
                'password' => '',
                'card_no' => '0000000000 ',
            ],
        ]);

    $service = new ZktDeviceUserSyncService($client);
    $results = $service->pullDeviceCredentials($this->device);

    expect($results)->toHaveCount(1)
        ->and($results[0]['status'])->toBe('updated')
        ->and($results[0]['updated_fields'])->toContain('privilege')
        ->and($this->employee->fresh()->device_privilege)->toBe(ZktDevicePrivilege::Enroller);
});
