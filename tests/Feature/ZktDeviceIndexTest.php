<?php

use App\Models\User;
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

    $superAdmin = Role::findOrCreate(PermissionRegistry::superAdminRole());
    $superAdmin->syncPermissions(PermissionRegistry::all());

    $this->user = User::factory()->create();
    $this->user->assignRole($superAdmin);
});

it('hides system devices from the attendance machines list', function () {
    ZktDevice::selfPunchDevice();
    ZktDevice::attendanceSheetDevice();

    $realDevice = ZktDevice::query()->create([
        'name' => 'Main Gate',
        'ip_address' => '192.168.1.10',
        'is_active' => true,
        'connection_status' => 'connected',
    ]);

    $response = $this->actingAs($this->user)->get('/zkt-devices');

    $response->assertOk();

    $names = collect($response->original->getData()['page']['props']['devices'])
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Main Gate'])
        ->and($names)->not->toContain('Mobile Punch')
        ->and($names)->not->toContain('Attendance Sheet');
});

it('blocks direct access to system device pages', function () {
    $systemDevice = ZktDevice::selfPunchDevice();

    $this->actingAs($this->user)
        ->get("/zkt-devices/{$systemDevice->id}")
        ->assertRedirect('/zkt-devices')
        ->assertSessionHas('error');
});
