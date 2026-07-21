<?php

use App\Enums\ZktMachineType;
use App\Models\RemoteDoorSite;
use App\Models\User;
use App\Models\ZktDevice;
use App\Models\ZktLocationGroup;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function zktDeviceUpdatePayload(ZktDevice $device, array $overrides = []): array
{
    return array_merge([
        'name' => $device->name,
        'brand' => $device->brand->value,
        'location' => $device->location,
        'machine_type' => $device->machine_type->value,
        'connection_mode' => $device->connection_mode->value,
        'ip_address' => $device->ip_address,
        'port' => $device->port,
        'protocol' => $device->protocol->value,
        'comm_password' => $device->comm_password,
        'serial_number' => $device->serial_number,
        'model_name' => $device->model_name,
        'firmware_version' => $device->firmware_version,
        'is_active' => $device->is_active,
        'auto_sync' => $device->auto_sync,
        'sync_interval_minutes' => $device->sync_interval_minutes,
        'connection_status' => $device->connection_status->value,
        'last_connected_at' => $device->last_connected_at,
        'notes' => $device->notes,
        'last_sync_error' => $device->last_sync_error,
        'tcpmux_enabled' => $device->tcpmux_enabled,
        'tcpmux_subdomain' => $device->tcpmux_subdomain,
        'tcpmux_port' => $device->tcpmux_port,
    ], $overrides);
}

beforeEach(function () {
    foreach (PermissionRegistry::all() as $permission) {
        Permission::findOrCreate($permission);
    }

    $superAdmin = Role::findOrCreate(PermissionRegistry::superAdminRole());
    $superAdmin->syncPermissions(PermissionRegistry::all());

    $this->user = User::factory()->create();
    $this->user->assignRole($superAdmin);

    $this->device = ZktDevice::query()->create([
        'name' => 'Lobby Reader',
        'ip_address' => '192.168.1.40',
        'machine_type' => ZktMachineType::Attendance,
        'is_active' => true,
        'connection_status' => 'unknown',
    ]);
});

it('allows machine type changes when the device is not linked anywhere', function () {
    $this->actingAs($this->user)
        ->put("/zkt-devices/{$this->device->id}", zktDeviceUpdatePayload($this->device, [
            'machine_type' => ZktMachineType::Access->value,
        ]))
        ->assertRedirect("/zkt-devices/{$this->device->id}");

    expect($this->device->fresh()->machine_type)->toBe(ZktMachineType::Access);
});

it('blocks machine type changes when the device is assigned to a location group', function () {
    $group = ZktLocationGroup::query()->create([
        'name' => 'HQ',
        'is_active' => true,
    ]);
    $group->devices()->attach($this->device->id);

    $this->actingAs($this->user)
        ->from("/zkt-devices/{$this->device->id}/edit")
        ->put("/zkt-devices/{$this->device->id}", zktDeviceUpdatePayload($this->device, [
            'machine_type' => ZktMachineType::Access->value,
        ]))
        ->assertRedirect("/zkt-devices/{$this->device->id}/edit")
        ->assertSessionHasErrors('machine_type');

    expect($this->device->fresh()->machine_type)->toBe(ZktMachineType::Attendance);
});

it('blocks machine type changes when the device is linked to remote access door sites', function () {
    RemoteDoorSite::query()->create([
        'zkt_device_id' => $this->device->id,
        'name' => 'Front Door',
        'latitude' => 4.17,
        'longitude' => 73.5,
        'radius_meters' => 100,
        'max_accuracy_meters' => 100,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->from("/zkt-devices/{$this->device->id}/edit")
        ->put("/zkt-devices/{$this->device->id}", zktDeviceUpdatePayload($this->device, [
            'machine_type' => ZktMachineType::Access->value,
        ]))
        ->assertRedirect("/zkt-devices/{$this->device->id}/edit")
        ->assertSessionHasErrors('machine_type');

    expect($this->device->fresh()->machine_type)->toBe(ZktMachineType::Attendance);
});

it('still allows other machine updates when machine type is locked', function () {
    $group = ZktLocationGroup::query()->create([
        'name' => 'HQ',
        'is_active' => true,
    ]);
    $group->devices()->attach($this->device->id);

    $this->actingAs($this->user)
        ->put("/zkt-devices/{$this->device->id}", zktDeviceUpdatePayload($this->device, [
            'name' => 'Lobby Reader Updated',
        ]))
        ->assertRedirect("/zkt-devices/{$this->device->id}");

    expect($this->device->fresh()->name)->toBe('Lobby Reader Updated')
        ->and($this->device->fresh()->machine_type)->toBe(ZktMachineType::Attendance);
});

it('marks machine type as locked on the edit page when linked to a location group', function () {
    $group = ZktLocationGroup::query()->create([
        'name' => 'HQ',
        'is_active' => true,
    ]);
    $group->devices()->attach($this->device->id);

    $response = $this->actingAs($this->user)->get("/zkt-devices/{$this->device->id}/edit");

    $response->assertOk();

    $device = $response->original->getData()['page']['props']['device'];

    expect($device['machine_type_locked'])->toBeTrue()
        ->and($device['machine_type_lock_reason'])->toContain('machine location group');
});
