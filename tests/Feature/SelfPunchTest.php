<?php

use App\Enums\AttendancePunchSource;
use App\Models\Employee;
use App\Models\SelfPunchSite;
use App\Models\User;
use App\Models\ZktAttendanceLog;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function selfPunchPayload(SelfPunchSite $site, array $overrides = []): array
{
    return array_merge([
        'self_punch_site_id' => $site->id,
        'punch_state' => 0,
        'latitude' => 4.1755,
        'longitude' => 73.5093,
        'accuracy_meters' => 20,
        'device_id' => '11111111-1111-4111-8111-111111111111',
    ], $overrides);
}

beforeEach(function () {
    foreach (PermissionRegistry::all() as $permission) {
        Permission::findOrCreate($permission);
    }

    $role = Role::findOrCreate('Self Punch Tester');
    $role->syncPermissions([
        'self-punch.use',
        'self-punch-sites.view',
        'self-punch-sites.create',
        'self-punch-sites.update',
        'self-punch-sites.delete',
    ]);

    $this->user = User::factory()->create();
    $this->user->assignRole($role);

    $this->employee = Employee::query()->create([
        'staff_id' => 'SP100',
        'name' => 'Self Puncher',
        'national_id' => 'NID-SP',
        'joined_date' => '2024-01-01',
        'gender' => 'female',
        'is_active' => true,
        'user_id' => $this->user->id,
        'bank_name' => 'Test Bank',
        'account_name' => 'Self Puncher',
        'account_no' => '123',
    ]);

    $this->site = SelfPunchSite::query()->create([
        'name' => 'Head Office',
        'latitude' => 4.1755,
        'longitude' => 73.5093,
        'radius_meters' => 100,
        'max_accuracy_meters' => 100,
        'require_public_ip' => false,
        'allowed_public_ips' => [],
        'is_active' => true,
    ]);
    $this->site->employees()->attach($this->employee->id);
});

it('records a self punch when the employee is at an assigned site', function () {
    $response = $this->actingAs($this->user)->post('/self-punch', selfPunchPayload($this->site));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $log = ZktAttendanceLog::query()->first();
    expect($log)->not->toBeNull()
        ->and($log->source)->toBe(AttendancePunchSource::SelfApp)
        ->and($log->device_user_id)->toBe('SP100')
        ->and($log->self_punch_site_id)->toBe($this->site->id)
        ->and($log->punch_state)->toBe(0)
        ->and($log->client_device_id)->toBe('11111111-1111-4111-8111-111111111111');
});

it('rejects self punch outside the geofence', function () {
    $response = $this->actingAs($this->user)->from('/self-punch')->post('/self-punch', selfPunchPayload($this->site, [
        'latitude' => 4.2,
        'longitude' => 73.6,
    ]));

    $response->assertRedirect('/self-punch');
    $response->assertSessionHasErrors('location');
    expect(ZktAttendanceLog::query()->count())->toBe(0);
});

it('rejects self punch when public ip restriction fails', function () {
    $this->site->update([
        'require_public_ip' => true,
        'allowed_public_ips' => ['203.0.113.10'],
    ]);

    $response = $this->actingAs($this->user)->from('/self-punch')->post('/self-punch', selfPunchPayload($this->site, [
        'public_ip' => '198.51.100.20',
    ]));

    $response->assertRedirect('/self-punch');
    $response->assertSessionHasErrors('network');
});

it('accepts self punch when browser reports an allowed public ip over lan', function () {
    $this->site->update([
        'require_public_ip' => true,
        'allowed_public_ips' => ['203.0.113.10'],
    ]);

    $response = $this->actingAs($this->user)->post('/self-punch', selfPunchPayload($this->site, [
        'public_ip' => '203.0.113.10',
    ]));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(ZktAttendanceLog::query()->first()?->client_ip)->toBe('203.0.113.10');
});

it('rejects self punch for employees not assigned to the site', function () {
    $this->site->employees()->detach($this->employee->id);

    $response = $this->actingAs($this->user)->from('/self-punch')->post('/self-punch', selfPunchPayload($this->site));

    $response->assertRedirect('/self-punch');
    $response->assertSessionHasErrors('self_punch_site_id');
});

it('creates a site with assigned employees and public ip list', function () {
    $response = $this->actingAs($this->user)->post('/self-punch-sites', [
        'name' => 'Warehouse',
        'code' => 'WH',
        'latitude' => 4.18,
        'longitude' => 73.51,
        'radius_meters' => 150,
        'max_accuracy_meters' => 80,
        'require_public_ip' => true,
        'allowed_public_ips_text' => "203.0.113.10\n203.0.113.0/24",
        'is_active' => true,
        'employee_ids' => [$this->employee->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $site = SelfPunchSite::query()->where('code', 'WH')->firstOrFail();
    expect($site->require_public_ip)->toBeTrue()
        ->and($site->allowed_public_ips)->toBe(['203.0.113.10', '203.0.113.0/24'])
        ->and($site->employees)->toHaveCount(1);
});

it('rejects self punch when another employee already punched from the same local ip today', function () {
    $otherUser = User::factory()->create();
    $otherUser->assignRole('Self Punch Tester');

    $otherEmployee = Employee::query()->create([
        'staff_id' => 'SP200',
        'name' => 'Other Puncher',
        'national_id' => 'NID-SP2',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'is_active' => true,
        'user_id' => $otherUser->id,
        'bank_name' => 'Test Bank',
        'account_name' => 'Other Puncher',
        'account_no' => '456',
    ]);
    $this->site->employees()->attach($otherEmployee->id);

    $sharedDeviceId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
        ->actingAs($this->user)
        ->post('/self-punch', selfPunchPayload($this->site, [
            'device_id' => $sharedDeviceId,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
        ->actingAs($otherUser)
        ->from('/self-punch')
        ->post('/self-punch', selfPunchPayload($this->site, [
            'device_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ]))
        ->assertRedirect('/self-punch')
        ->assertSessionHasErrors('network');

    expect(ZktAttendanceLog::query()->count())->toBe(1);
});

it('allows different employees to self punch from different local ips on the same day', function () {
    $otherUser = User::factory()->create();
    $otherUser->assignRole('Self Punch Tester');

    $otherEmployee = Employee::query()->create([
        'staff_id' => 'SP201',
        'name' => 'Second Puncher',
        'national_id' => 'NID-SP3',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'is_active' => true,
        'user_id' => $otherUser->id,
        'bank_name' => 'Test Bank',
        'account_name' => 'Second Puncher',
        'account_no' => '789',
    ]);
    $this->site->employees()->attach($otherEmployee->id);

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
        ->actingAs($this->user)
        ->post('/self-punch', selfPunchPayload($this->site, [
            'device_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        ]))
        ->assertSessionHas('success');

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.51'])
        ->actingAs($otherUser)
        ->post('/self-punch', selfPunchPayload($this->site, [
            'device_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
        ]))
        ->assertSessionHas('success');

    expect(ZktAttendanceLog::query()->count())->toBe(2);
});

it('rejects self punch when another employee already punched from the same browser device id today', function () {
    $otherUser = User::factory()->create();
    $otherUser->assignRole('Self Punch Tester');

    $otherEmployee = Employee::query()->create([
        'staff_id' => 'SP202',
        'name' => 'Shared Browser User',
        'national_id' => 'NID-SP4',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'is_active' => true,
        'user_id' => $otherUser->id,
        'bank_name' => 'Test Bank',
        'account_name' => 'Shared Browser User',
        'account_no' => '999',
    ]);
    $this->site->employees()->attach($otherEmployee->id);

    $sharedDeviceId = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';

    $this->actingAs($this->user)
        ->post('/self-punch', selfPunchPayload($this->site, [
            'device_id' => $sharedDeviceId,
        ]))
        ->assertSessionHas('success');

    $this->actingAs($otherUser)
        ->from('/self-punch')
        ->post('/self-punch', selfPunchPayload($this->site, [
            'device_id' => $sharedDeviceId,
        ]))
        ->assertRedirect('/self-punch')
        ->assertSessionHasErrors('device_id');

    expect(ZktAttendanceLog::query()->count())->toBe(1);
});
