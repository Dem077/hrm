<?php

use App\Models\Employee;
use App\Models\User;
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

it('requires bank details when creating an employee', function () {
    $response = $this->actingAs($this->user)->post('/employees', [
        'staff_id' => 'EMP200',
        'name' => 'Jane Doe',
        'national_id' => 'NID200',
        'email' => 'jane@example.com',
        'mobile_number' => '',
        'joined_date' => '2024-01-01',
        'gender' => 'female',
        'grade_id' => '',
        'manager_id' => '',
        'is_active' => true,
        'works_saturday' => false,
        'duty_type' => 'normal',
        'uses_custom_duty_times' => false,
        'device_privilege' => 'employee',
    ]);

    $response->assertSessionHasErrors(['bank_name', 'account_name', 'account_no']);
});

it('stores profile details when creating an employee', function () {
    $response = $this->actingAs($this->user)->post('/employees', [
        'staff_id' => 'EMP201',
        'name' => 'Jane Doe',
        'national_id' => 'NID201',
        'email' => 'jane201@example.com',
        'mobile_number' => '7700000',
        'joined_date' => '2020-06-01',
        'gender' => 'female',
        'grade_id' => '',
        'manager_id' => '',
        'is_active' => true,
        'works_saturday' => false,
        'duty_type' => 'normal',
        'uses_custom_duty_times' => false,
        'device_privilege' => 'employee',
        'current_address' => '123 Main Street',
        'personal_email' => 'jane.personal@example.com',
        'marital_status' => 'married',
        'blood_group' => 'a_positive',
        'date_of_birth' => '1990-05-15',
        'employment_type' => 'permanent',
        ...employeeBankPayload([
            'bank_name' => 'CBM',
            'account_name' => 'Jane Doe',
            'account_no' => '9876543210',
        ]),
    ]);

    $response->assertRedirect(route('employees.index'));

    $employee = Employee::query()->where('staff_id', 'EMP201')->firstOrFail();

    expect($employee->current_address)->toBe('123 Main Street')
        ->and($employee->personal_email)->toBe('jane.personal@example.com')
        ->and($employee->marital_status?->value)->toBe('married')
        ->and($employee->blood_group?->value)->toBe('a_positive')
        ->and($employee->employment_type?->value)->toBe('permanent')
        ->and($employee->bank_name)->toBe('CBM')
        ->and($employee->lengthOfServiceLabel())->not->toBeNull();
});

it('computes length of service from joined date', function () {
    $employee = Employee::query()->create([
        'staff_id' => 'EMP202',
        'name' => 'John Doe',
        'national_id' => 'NID202',
        'email' => 'john202@example.com',
        'joined_date' => now()->subYears(2)->subMonths(3)->toDateString(),
        'gender' => 'male',
        'is_active' => true,
    ]);

    expect($employee->lengthOfServiceLabel())->toContain('2 years')
        ->and($employee->lengthOfServiceLabel())->toContain('3 months');
});
