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
            'Company Structure' => [
                'company-structure.view' => 'View company structure',
                'company-structure.create' => 'Create company structure items',
                'company-structure.update' => 'Update company structure items',
                'company-structure.delete' => 'Delete company structure items',
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
            'Overtime' => [
                'overtime-requests.view' => 'View overtime requests',
                'overtime-requests.create' => 'Apply for overtime',
                'overtime-requests.approve' => 'Approve overtime requests (manager/HOD)',
                'overtime-requests.approve-hr' => 'Final HR approval for overtime',
                'overtime-requests.cancel' => 'Cancel own pending overtime',
                'overtime-requests.view-all' => 'View all overtime requests',
            ],
            'Leave Balances' => [
                'leave-balances.view' => 'View employee leave balances',
                'leave-balances.manual-carry-forward' => 'Carry forward leave manually for specific employee',
            ],
            'Payroll Structure' => [
                'payroll-structure.view' => 'View payroll structure',
                'payroll-structure.update' => 'Manage payroll components and designation salary structures',
            ],
            'Payroll Processing' => [
                'payroll.view' => 'View payroll processing page',
                'payroll.create' => 'Create payroll draft runs',
                'payroll.delete' => 'Delete draft payroll runs',
                'payroll.adjust' => 'Update payroll manual adjustments',
                'payroll.process' => 'Run payroll processing for draft runs',
                'payroll.finalize' => 'Finalise and reopen payroll runs',
                'payroll.export' => 'Export payroll sheets',
                'payroll.audit.view' => 'View payroll change audit logs',
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
                'zkt-devices.manage-users' => 'Push and remove user profiles on machines',
            ],
            'Machine Location Groups' => [
                'zkt-location-groups.view' => 'View machine location groups',
                'zkt-location-groups.create' => 'Create machine location groups',
                'zkt-location-groups.update' => 'Update machine location groups',
                'zkt-location-groups.delete' => 'Delete machine location groups',
                'zkt-location-groups.sync-users' => 'Sync assigned employees to group machines',
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
            'Mobile Punch' => [
                'self-punch.use' => 'Punch in/out and open doors from the mobile app at allowed sites',
                'mobile-punch-logs.view' => 'View mobile punch access audit logs',
                'self-punch-sites.view' => 'View mobile punch and remote door sites',
                'self-punch-sites.create' => 'Create mobile punch and remote door sites',
                'self-punch-sites.update' => 'Update mobile punch and remote door sites and employee assignments',
                'self-punch-sites.delete' => 'Delete mobile punch and remote door sites',
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
                'attendance-settings.leave-carry-forward.update' => 'Update leave carry-forward setting',
                'attendance-settings.leave-workflow.update' => 'Update leave and overtime approval workflows by branch',
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
