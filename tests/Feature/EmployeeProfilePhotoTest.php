<?php

use App\Models\Employee;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    Storage::fake('public');
});

it('uploads a profile photo when creating an employee', function () {
    $photo = UploadedFile::fake()->image('profile.jpg', 200, 200);

    $response = $this->actingAs($this->user)->post('/employees', [
        'staff_id' => 'EMP100',
        'name' => 'Jane Doe',
        'national_id' => 'NID100',
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
        'device_privilege' => 'employee',
        'profile_photo' => $photo,
        ...employeeBankPayload(),
    ]);

    $response->assertRedirect(route('employees.index'));

    $employee = Employee::query()->where('staff_id', 'EMP100')->firstOrFail();

    expect($employee->profile_photo_path)->not->toBeNull()
        ->and($employee->profilePhotoUrl())->not->toBeNull();

    Storage::disk('public')->assertExists($employee->profile_photo_path);
});

it('updates and removes an employee profile photo', function () {
    $employee = Employee::query()->create([
        'staff_id' => 'EMP101',
        'name' => 'John Doe',
        'national_id' => 'NID101',
        'email' => 'john@example.com',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'is_active' => true,
    ]);

    $photo = UploadedFile::fake()->image('profile.png', 180, 180);

    $this->actingAs($this->user)->put("/employees/{$employee->id}", [
        'staff_id' => $employee->staff_id,
        'name' => $employee->name,
        'national_id' => $employee->national_id,
        'email' => $employee->email,
        'mobile_number' => '',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'department_id' => '',
        'designation_id' => '',
        'manager_id' => '',
        'is_active' => true,
        'works_saturday' => false,
        'duty_type' => 'normal',
        'uses_custom_duty_times' => false,
        'device_privilege' => 'employee',
        'profile_photo' => $photo,
        ...employeeBankPayload(),
    ])->assertRedirect(route('employees.show', $employee));

    $employee->refresh();

    expect($employee->profile_photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($employee->profile_photo_path);

    $this->actingAs($this->user)->put("/employees/{$employee->id}", [
        'staff_id' => $employee->staff_id,
        'name' => $employee->name,
        'national_id' => $employee->national_id,
        'email' => $employee->email,
        'mobile_number' => '',
        'joined_date' => '2024-01-01',
        'gender' => 'male',
        'department_id' => '',
        'designation_id' => '',
        'manager_id' => '',
        'is_active' => true,
        'works_saturday' => false,
        'duty_type' => 'normal',
        'uses_custom_duty_times' => false,
        'device_privilege' => 'employee',
        'remove_profile_photo' => true,
        ...employeeBankPayload(),
    ])->assertRedirect(route('employees.show', $employee));

    $employee->refresh();

    expect($employee->profile_photo_path)->toBeNull();
});
