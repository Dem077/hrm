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
            'Leave Types' => [
                'leave-types.view' => 'View leave types',
                'leave-types.create' => 'Create leave types',
                'leave-types.update' => 'Update leave types',
                'leave-types.delete' => 'Delete leave types',
            ],
            'Leave Management' => [
                'leave-requests.view' => 'View leave requests',
                'leave-requests.create' => 'Apply for leave',
                'leave-requests.approve' => 'Approve leave requests (manager/HOD)',
                'leave-requests.approve-hr' => 'Final HR approval for leave',
                'leave-requests.cancel' => 'Cancel own pending leave',
                'leave-requests.view-all' => 'View all leave requests',
                'leave-requests.record-for-others' => 'Record leave for any employee (auto-approved)',
            ],
            'Leave Balances' => [
                'leave-balances.view' => 'View employee leave balances',
            ],
            'Payroll Structure' => [
                'payroll-structure.view' => 'View payroll structure',
                'payroll-structure.update' => 'Manage payroll components and designations',
            ],
            'Attendance Machines' => [
                'zkt-devices.view' => 'View machines',
                'zkt-devices.create' => 'Create machines',
                'zkt-devices.update' => 'Update machines',
                'zkt-devices.delete' => 'Delete machines',
                'zkt-devices.probe' => 'Probe machines',
                'zkt-devices.test' => 'Test connection',
                'zkt-devices.sync' => 'Sync punches',
                'zkt-devices.sync-all' => 'Sync all active machines',
                'zkt-devices.read-time' => 'Read machine clock',
                'zkt-devices.sync-time' => 'Sync machine clock',
            ],
            'Punch Logs' => [
                'zkt-attendance-logs.view' => 'View punch logs',
            ],
            'Attendance Sheet' => [
                'attendance-sheet.view' => 'View attendance sheet',
                'attendance-sheet.view-all' => 'View all employees on attendance sheet',
                'attendance-sheet.add-punch' => 'Add manual punch records from attendance sheet',
                'attendance-sheet.remove-punch' => 'Remove punch records from attendance sheet',
            ],
            'Duty Roster' => [
                'duty-rosters.view' => 'View duty roster',
                'duty-rosters.view-all' => 'View and manage duty roster for all departments',
                'duty-rosters.create' => 'Create duty roster entries',
                'duty-rosters.update' => 'Update duty roster entries',
                'duty-rosters.delete' => 'Delete duty roster entries',
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
            'App Settings' => [
                'app-settings.view' => 'View app settings',
                'app-settings.update' => 'Update app logo and colors',
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
