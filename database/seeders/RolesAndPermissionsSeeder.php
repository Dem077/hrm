<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionRegistry::all() as $permission) {
            Permission::findOrCreate($permission);
        }

        $superAdmin = Role::findOrCreate(PermissionRegistry::superAdminRole());
        $superAdmin->syncPermissions(PermissionRegistry::all());

        User::query()->each(function (User $user) use ($superAdmin): void {
            if ($user->roles()->doesntExist()) {
                $user->assignRole($superAdmin);
            }
        });
    }
}
