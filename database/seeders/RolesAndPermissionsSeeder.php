<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionRegistry;
use App\Support\PermissionSync;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        PermissionSync::syncRegistry();

        $superAdmin = Role::findOrCreate(PermissionRegistry::superAdminRole());

        User::query()->each(function (User $user) use ($superAdmin): void {
            if ($user->roles()->doesntExist()) {
                $user->assignRole($superAdmin);
            }
        });
    }
}
