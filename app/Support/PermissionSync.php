<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Schema;

class PermissionSync
{
    public static function syncRegistry(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionRegistry::all() as $permission) {
            Permission::findOrCreate($permission);
        }

        $superAdmin = Role::findOrCreate(PermissionRegistry::superAdminRole());
        $registered = PermissionRegistry::all();

        if ($superAdmin->permissions()->count() !== count($registered)) {
            $superAdmin->syncPermissions($registered);
        }
    }
}
