<?php

namespace App\Support;

class PermissionRegistry
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'View dashboard',
            ],
            'Employees' => [
                'employees.view' => 'View employees',
                'employees.create' => 'Create employees',
                'employees.update' => 'Update employees',
                'employees.delete' => 'Delete employees',
            ],
            'Departments' => [
                'departments.view' => 'View departments',
                'departments.create' => 'Create departments',
                'departments.update' => 'Update departments',
                'departments.delete' => 'Delete departments',
            ],
            'ZKT Devices' => [
                'zkt-devices.view' => 'View devices',
                'zkt-devices.create' => 'Create devices',
                'zkt-devices.update' => 'Update devices',
                'zkt-devices.delete' => 'Delete devices',
                'zkt-devices.probe' => 'Probe devices',
                'zkt-devices.test' => 'Test connection',
                'zkt-devices.sync' => 'Sync punches',
                'zkt-devices.sync-all' => 'Sync all devices',
                'zkt-devices.read-time' => 'Read device clock',
                'zkt-devices.sync-time' => 'Sync device clock',
            ],
            'Punch Logs' => [
                'zkt-attendance-logs.view' => 'View punch logs',
            ],
            'Attendance Sheet' => [
                'attendance-sheet.view' => 'View attendance sheet',
                'attendance-sheet.view-all' => 'View all employees on attendance sheet',
            ],
            'Global Settings' => [
                'attendance-settings.view' => 'View attendance settings',
                'attendance-settings.payroll-period.update' => 'Update payroll period',
                'attendance-settings.duty-policies.create' => 'Add duty policies',
                'attendance-settings.duty-policies.update' => 'Update duty policies',
                'attendance-settings.duty-policies.delete' => 'Delete duty policies',
                'attendance-settings.holidays.create' => 'Add public holidays',
                'attendance-settings.holidays.update' => 'Update public holidays',
                'attendance-settings.holidays.delete' => 'Delete public holidays',
            ],
            'Roles & Access' => [
                'roles.view' => 'View roles',
                'roles.create' => 'Create roles',
                'roles.update' => 'Update roles',
                'roles.delete' => 'Delete roles',
                'users.assign-roles' => 'Assign roles to users',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::grouped() as $groupPermissions) {
            foreach ($groupPermissions as $name => $label) {
                $permissions[] = $name;
            }
        }

        return $permissions;
    }

    public static function superAdminRole(): string
    {
        return 'Super Admin';
    }
}
