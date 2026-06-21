<?php

use App\Enums\ZktDevicePrivilege;

it('maps device privileges to ZKT role values', function () {
    expect(ZktDevicePrivilege::Employee->deviceRole())->toBe(0)
        ->and(ZktDevicePrivilege::Enroller->deviceRole())->toBe(4)
        ->and(ZktDevicePrivilege::Administrator->deviceRole())->toBe(14);
});

it('maps known device roles back to privileges', function () {
    expect(ZktDevicePrivilege::tryFromDeviceRole(0))->toBe(ZktDevicePrivilege::Employee)
        ->and(ZktDevicePrivilege::tryFromDeviceRole(4))->toBe(ZktDevicePrivilege::Enroller)
        ->and(ZktDevicePrivilege::tryFromDeviceRole(14))->toBe(ZktDevicePrivilege::Administrator)
        ->and(ZktDevicePrivilege::tryFromDeviceRole(8))->toBeNull();
});

it('exposes employee, enroller, and administrator options', function () {
    expect(ZktDevicePrivilege::options())->toBe([
        ['value' => 'employee', 'label' => 'Employee'],
        ['value' => 'enroller', 'label' => 'Enroller'],
        ['value' => 'administrator', 'label' => 'Administrator'],
    ]);
});
