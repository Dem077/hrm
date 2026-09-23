<?php

use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\AttendanceSettingController;
use App\Http\Controllers\AttendanceSheetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\NationalityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CompanyStructureController;
use App\Http\Controllers\GradePayrollController;
use App\Http\Controllers\StructureGradeController;
use App\Http\Controllers\StructureLevelController;
use App\Http\Controllers\StructureNodeController;
use App\Http\Controllers\DutyRosterController;
use App\Http\Controllers\DutyShiftTemplateController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\OvertimeRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\MobilePunchAccessLogController;
use App\Http\Controllers\PayrollComponentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollStructureController;
use App\Http\Controllers\RemoteDoorSiteController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SelfPunchController;
use App\Http\Controllers\SelfPunchSiteController;
use App\Http\Controllers\ZktAttendanceLogController;
use App\Http\Controllers\ZktDeviceController;
use App\Http\Controllers\ZktLocationGroupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Authentication is login-only. User registration is intentionally disabled;
| administrators create accounts via `php artisan make:user` or tinker.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::middleware('permission:employees.view')->group(function () {
        Route::get('employees/sample-csv', [EmployeeController::class, 'downloadSample'])
            ->middleware('permission:employees.create')
            ->name('employees.sample-csv');
        Route::post('employees/import/preview', [EmployeeController::class, 'previewImport'])
            ->middleware('permission:employees.create')
            ->name('employees.import.preview');
        Route::post('employees/import', [EmployeeController::class, 'import'])
            ->middleware('permission:employees.create')
            ->name('employees.import');
        Route::post('employees/import/cancel', [EmployeeController::class, 'cancelImport'])
            ->middleware('permission:employees.create')
            ->name('employees.import.cancel');
        Route::post('employees/bulk-update', [EmployeeController::class, 'bulkUpdate'])
            ->middleware('permission:employees.update')
            ->name('employees.bulk-update');

        Route::resource('employees', EmployeeController::class)->middleware([
            'index' => 'permission:employees.view',
            'show' => 'permission:employees.view',
            'create' => 'permission:employees.create',
            'store' => 'permission:employees.create',
            'edit' => 'permission:employees.update',
            'update' => 'permission:employees.update',
            'destroy' => 'permission:employees.delete',
        ]);
        Route::post('employees/{employee}/sync-devices', [EmployeeController::class, 'syncDevices'])
            ->middleware('permission:zkt-devices.manage-users')
            ->name('employees.sync-devices');
        Route::post('employees/{employee}/pull-device-credentials', [EmployeeController::class, 'pullDeviceCredentials'])
            ->middleware('permission:zkt-devices.manage-users')
            ->name('employees.pull-device-credentials');

        Route::post('nationalities', [NationalityController::class, 'store'])
            ->middleware('permission:employees.create|employees.update')
            ->name('nationalities.store');
        Route::delete('nationalities/{nationality}', [NationalityController::class, 'destroy'])
            ->middleware('permission:employees.create|employees.update')
            ->name('nationalities.destroy');
    });

    Route::middleware('permission:company-structure.view')->group(function () {
        Route::get('company-structure', [CompanyStructureController::class, 'index'])
            ->name('company-structure.index');
        Route::get('company-structure/chart', [CompanyStructureController::class, 'chart'])
            ->name('company-structure.chart');
        Route::get('company-structure/designations', [StructureGradeController::class, 'index'])
            ->name('company-structure.designations.index');

        Route::get('company-structure/sample-csv', [CompanyStructureController::class, 'downloadSample'])
            ->name('company-structure.sample-csv');
        Route::post('company-structure/import/preview', [CompanyStructureController::class, 'previewImport'])
            ->middleware('permission:company-structure.create')
            ->name('company-structure.import.preview');
        Route::post('company-structure/import', [CompanyStructureController::class, 'import'])
            ->middleware('permission:company-structure.create')
            ->name('company-structure.import');
        Route::post('company-structure/import/cancel', [CompanyStructureController::class, 'cancelImport'])
            ->middleware('permission:company-structure.create')
            ->name('company-structure.import.cancel');

        Route::post('company-structure/groups/{structure_group}/nodes', [StructureNodeController::class, 'store'])
            ->middleware('permission:company-structure.create')
            ->name('company-structure.nodes.store');
        Route::put('company-structure/nodes/{structure_node}', [StructureNodeController::class, 'update'])
            ->middleware('permission:company-structure.update')
            ->name('company-structure.nodes.update');
        Route::delete('company-structure/nodes/{structure_node}', [StructureNodeController::class, 'destroy'])
            ->middleware('permission:company-structure.delete')
            ->name('company-structure.nodes.destroy');
        Route::post('company-structure/nodes/{structure_node}/move', [StructureNodeController::class, 'move'])
            ->middleware('permission:company-structure.update')
            ->name('company-structure.nodes.move');
        Route::post('company-structure/nodes/reorder', [StructureNodeController::class, 'reorder'])
            ->middleware('permission:company-structure.update')
            ->name('company-structure.nodes.reorder');

        Route::post('company-structure/levels', [StructureLevelController::class, 'store'])
            ->middleware('permission:company-structure.create')
            ->name('company-structure.levels.store');
        Route::put('company-structure/levels/{structure_level}', [StructureLevelController::class, 'update'])
            ->middleware('permission:company-structure.update')
            ->name('company-structure.levels.update');
        Route::delete('company-structure/levels/{structure_level}', [StructureLevelController::class, 'destroy'])
            ->middleware('permission:company-structure.delete')
            ->name('company-structure.levels.destroy');
        Route::post('company-structure/levels/reorder', [StructureLevelController::class, 'reorder'])
            ->middleware('permission:company-structure.update')
            ->name('company-structure.levels.reorder');

        Route::post('company-structure/levels/{structure_level}/grades', [StructureGradeController::class, 'store'])
            ->middleware('permission:company-structure.create')
            ->name('company-structure.grades.store');
        Route::put('company-structure/grades/{structure_grade}', [StructureGradeController::class, 'update'])
            ->middleware('permission:company-structure.update')
            ->name('company-structure.grades.update');
        Route::put('company-structure/grades/{structure_grade}/details', [StructureGradeController::class, 'updateDetails'])
            ->middleware('permission:company-structure.update')
            ->name('company-structure.grades.details.update');
        Route::delete('company-structure/grades/{structure_grade}', [StructureGradeController::class, 'destroy'])
            ->middleware('permission:company-structure.delete')
            ->name('company-structure.grades.destroy');
        Route::post('company-structure/levels/{structure_level}/grades/reorder', [StructureGradeController::class, 'reorder'])
            ->middleware('permission:company-structure.update')
            ->name('company-structure.grades.reorder');
    });

    Route::middleware('permission:zkt-devices.view')->group(function () {
        Route::post('zkt-devices/probe', [ZktDeviceController::class, 'probe'])
            ->middleware('permission:zkt-devices.probe')
            ->name('zkt-devices.probe');
        Route::post('zkt-devices/sync-all', [ZktDeviceController::class, 'syncAll'])
            ->middleware('permission:zkt-devices.sync-all')
            ->name('zkt-devices.sync-all');
        Route::post('zkt-devices/{zkt_device}/test', [ZktDeviceController::class, 'test'])
            ->middleware('permission:zkt-devices.test')
            ->name('zkt-devices.test');
        Route::post('zkt-devices/{zkt_device}/sync', [ZktDeviceController::class, 'sync'])
            ->middleware('permission:zkt-devices.sync')
            ->name('zkt-devices.sync');
        Route::post('zkt-devices/{zkt_device}/read-time', [ZktDeviceController::class, 'readTime'])
            ->middleware('permission:zkt-devices.read-time')
            ->name('zkt-devices.read-time');
        Route::post('zkt-devices/{zkt_device}/sync-time', [ZktDeviceController::class, 'syncTime'])
            ->middleware('permission:zkt-devices.sync-time')
            ->name('zkt-devices.sync-time');
        Route::post('zkt-devices/{zkt_device}/sync-users', [ZktDeviceController::class, 'syncUsers'])
            ->middleware('permission:zkt-devices.manage-users')
            ->name('zkt-devices.sync-users');
        Route::post('zkt-devices/{zkt_device}/pull-users', [ZktDeviceController::class, 'pullUsers'])
            ->middleware('permission:zkt-devices.manage-users')
            ->name('zkt-devices.pull-users');
        Route::resource('zkt-devices', ZktDeviceController::class)->middleware([
            'index' => 'permission:zkt-devices.view',
            'show' => 'permission:zkt-devices.view',
            'create' => 'permission:zkt-devices.create',
            'store' => 'permission:zkt-devices.create',
            'edit' => 'permission:zkt-devices.update',
            'update' => 'permission:zkt-devices.update',
            'destroy' => 'permission:zkt-devices.delete',
        ]);
    });

    Route::middleware('permission:zkt-location-groups.view')->group(function () {
        Route::get('zkt-location-groups', [ZktLocationGroupController::class, 'index'])->name('zkt-location-groups.index');
        Route::post('zkt-location-groups', [ZktLocationGroupController::class, 'store'])
            ->middleware('permission:zkt-location-groups.create')
            ->name('zkt-location-groups.store');
        Route::put('zkt-location-groups/{zkt_location_group}', [ZktLocationGroupController::class, 'update'])
            ->middleware('permission:zkt-location-groups.update')
            ->name('zkt-location-groups.update');
        Route::delete('zkt-location-groups/{zkt_location_group}', [ZktLocationGroupController::class, 'destroy'])
            ->middleware('permission:zkt-location-groups.delete')
            ->name('zkt-location-groups.destroy');
        Route::post('zkt-location-groups/{zkt_location_group}/sync-users', [ZktLocationGroupController::class, 'syncUsers'])
            ->middleware('permission:zkt-location-groups.sync-users')
            ->name('zkt-location-groups.sync-users');
    });

    Route::middleware('permission:attendance-sheet.view')->group(function () {
        Route::get('attendance-sheet', [AttendanceSheetController::class, 'index'])
            ->name('attendance-sheet.index');
        Route::post('attendance-sheet/manual-punches', [AttendanceSheetController::class, 'storeManualPunch'])
            ->middleware('permission:attendance-sheet.add-punch')
            ->name('attendance-sheet.manual-punches.store');
        Route::delete('attendance-sheet/manual-punches', [AttendanceSheetController::class, 'destroyManualPunches'])
            ->middleware('permission:attendance-sheet.remove-punch')
            ->name('attendance-sheet.manual-punches.destroy');
    });

    Route::middleware('permission:self-punch.use')->group(function () {
        Route::get('self-punch', [SelfPunchController::class, 'index'])->name('self-punch.index');
        Route::post('self-punch', [SelfPunchController::class, 'store'])->name('self-punch.store');
        Route::post('self-punch/open-door', [SelfPunchController::class, 'openDoor'])->name('self-punch.open-door');
    });

    Route::middleware('permission:self-punch-sites.view')->group(function () {
        Route::get('self-punch-sites', [SelfPunchSiteController::class, 'index'])->name('self-punch-sites.index');
        Route::post('self-punch-sites', [SelfPunchSiteController::class, 'store'])
            ->middleware('permission:self-punch-sites.create')
            ->name('self-punch-sites.store');
        Route::put('self-punch-sites/{self_punch_site}', [SelfPunchSiteController::class, 'update'])
            ->middleware('permission:self-punch-sites.update')
            ->name('self-punch-sites.update');
        Route::delete('self-punch-sites/{self_punch_site}', [SelfPunchSiteController::class, 'destroy'])
            ->middleware('permission:self-punch-sites.delete')
            ->name('self-punch-sites.destroy');
        Route::post('remote-door-sites', [RemoteDoorSiteController::class, 'store'])
            ->middleware('permission:self-punch-sites.create')
            ->name('remote-door-sites.store');
        Route::put('remote-door-sites/{remote_door_site}', [RemoteDoorSiteController::class, 'update'])
            ->middleware('permission:self-punch-sites.update')
            ->name('remote-door-sites.update');
        Route::delete('remote-door-sites/{remote_door_site}', [RemoteDoorSiteController::class, 'destroy'])
            ->middleware('permission:self-punch-sites.delete')
            ->name('remote-door-sites.destroy');
    });

    Route::middleware('permission:duty-rosters.view')->group(function () {
        Route::get('duty-rosters', [DutyRosterController::class, 'index'])->name('duty-rosters.index');
        Route::post('duty-rosters/bulk-assign', [DutyRosterController::class, 'bulkAssign'])
            ->middleware('permission:duty-rosters.create')
            ->name('duty-rosters.bulk-assign');
        Route::post('duty-rosters', [DutyRosterController::class, 'store'])
            ->middleware('permission:duty-rosters.create')
            ->name('duty-rosters.store');
        Route::put('duty-rosters/{duty_roster}', [DutyRosterController::class, 'update'])
            ->middleware('permission:duty-rosters.update')
            ->name('duty-rosters.update');
        Route::delete('duty-rosters/{duty_roster}', [DutyRosterController::class, 'destroy'])
            ->middleware('permission:duty-rosters.delete')
            ->name('duty-rosters.destroy');

        Route::post('duty-shift-templates', [DutyShiftTemplateController::class, 'store'])
            ->middleware('permission:duty-rosters.create')
            ->name('duty-shift-templates.store');
        Route::put('duty-shift-templates/{duty_shift_template}', [DutyShiftTemplateController::class, 'update'])
            ->middleware('permission:duty-rosters.update')
            ->name('duty-shift-templates.update');
        Route::delete('duty-shift-templates/{duty_shift_template}', [DutyShiftTemplateController::class, 'destroy'])
            ->middleware('permission:duty-rosters.delete')
            ->name('duty-shift-templates.destroy');
    });

    Route::middleware('permission:leave-requests.view|leave-requests.approve|leave-requests.approve-hr')->group(function () {
        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
        Route::get('leave-requests/record', [LeaveRequestController::class, 'createForStaff'])
            ->middleware('permission:leave-requests.record-for-others')
            ->name('leave-requests.record.create');
        Route::post('leave-requests/record', [LeaveRequestController::class, 'storeForStaff'])
            ->middleware('permission:leave-requests.record-for-others')
            ->name('leave-requests.record.store');
        Route::post('leave-requests/check-punches', [LeaveRequestController::class, 'checkPunches'])
            ->middleware('permission:leave-requests.create|leave-requests.record-for-others')
            ->name('leave-requests.check-punches');
        Route::get('leave-requests/annual-balance', [LeaveRequestController::class, 'annualBalance'])
            ->middleware('permission:leave-requests.create|leave-requests.record-for-others')
            ->name('leave-requests.annual-balance');
        Route::get('leave-requests/create', [LeaveRequestController::class, 'create'])
            ->middleware('permission:leave-requests.create')
            ->name('leave-requests.create');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])
            ->middleware('permission:leave-requests.create')
            ->name('leave-requests.store');
        Route::get('leave-requests/{leave_request}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
        Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve'])
            ->middleware('permission:leave-requests.approve|leave-requests.approve-hr')
            ->name('leave-requests.approve');
        Route::post('leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject'])
            ->middleware('permission:leave-requests.approve|leave-requests.approve-hr')
            ->name('leave-requests.reject');
        Route::post('leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel'])
            ->middleware('permission:leave-requests.cancel')
            ->name('leave-requests.cancel');
    });

    Route::middleware('permission:overtime-requests.view|overtime-requests.approve|overtime-requests.approve-hr')->group(function () {
        Route::get('overtime-requests', [OvertimeRequestController::class, 'index'])->name('overtime-requests.index');
        Route::get('overtime-requests/create', [OvertimeRequestController::class, 'create'])
            ->middleware('permission:overtime-requests.create')
            ->name('overtime-requests.create');
        Route::post('overtime-requests', [OvertimeRequestController::class, 'store'])
            ->middleware('permission:overtime-requests.create')
            ->name('overtime-requests.store');
        Route::get('overtime-requests/{overtime_request}', [OvertimeRequestController::class, 'show'])->name('overtime-requests.show');
        Route::post('overtime-requests/{overtime_request}/approve', [OvertimeRequestController::class, 'approve'])
            ->middleware('permission:overtime-requests.approve|overtime-requests.approve-hr')
            ->name('overtime-requests.approve');
        Route::post('overtime-requests/{overtime_request}/reject', [OvertimeRequestController::class, 'reject'])
            ->middleware('permission:overtime-requests.approve|overtime-requests.approve-hr')
            ->name('overtime-requests.reject');
        Route::post('overtime-requests/{overtime_request}/cancel', [OvertimeRequestController::class, 'cancel'])
            ->middleware('permission:overtime-requests.cancel')
            ->name('overtime-requests.cancel');
    });

    Route::middleware('permission:leave-balances.view')->group(function () {
        Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
        Route::get('leave-balances/export-all', [LeaveBalanceController::class, 'exportAll'])->name('leave-balances.export-all');
        Route::get('leave-balances/export', [LeaveBalanceController::class, 'export'])->name('leave-balances.export');
        Route::post('leave-balances/manual-carry-forward', [LeaveBalanceController::class, 'storeCarryForward'])
            ->middleware('permission:leave-balances.manual-carry-forward')
            ->name('leave-balances.manual-carry-forward.store');
    });

    Route::middleware('permission:leave-types.view')->group(function () {
        Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::post('leave-types', [LeaveTypeController::class, 'store'])
            ->middleware('permission:leave-types.create')
            ->name('leave-types.store');
        Route::put('leave-types/{leave_type}', [LeaveTypeController::class, 'update'])
            ->middleware('permission:leave-types.update')
            ->name('leave-types.update');
        Route::delete('leave-types/{leave_type}', [LeaveTypeController::class, 'destroy'])
            ->middleware('permission:leave-types.delete')
            ->name('leave-types.destroy');
    });

    Route::middleware('permission:payroll-structure.view')->group(function () {
        Route::get('payroll-structure', [PayrollStructureController::class, 'index'])->name('payroll-structure.index');
        Route::post('payroll-structure/components', [PayrollComponentController::class, 'store'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.components.store');
        Route::put('payroll-structure/components/{payroll_component}', [PayrollComponentController::class, 'update'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.components.update');
        Route::put('payroll-structure/components/{payroll_component}/global-rate', [PayrollComponentController::class, 'updateGlobalRate'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.components.global-rate');
        Route::delete('payroll-structure/components/{payroll_component}', [PayrollComponentController::class, 'destroy'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.components.destroy');
        Route::put('payroll-structure/grades/{structure_grade}', [GradePayrollController::class, 'update'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.grades.update');
    });

    Route::middleware('permission:payroll.view')->group(function () {
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('payroll/{payroll_run}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('payroll/{payroll_run}/employees/{employee}/attendance', [PayrollController::class, 'employeeAttendance'])
            ->name('payroll.employees.attendance');
        Route::get('payroll/{payroll_run}/employees/{employee}/adjustments', [PayrollController::class, 'employeeAdjustments'])
            ->name('payroll.employees.adjustments');
        Route::post('payroll', [PayrollController::class, 'store'])
            ->middleware('permission:payroll.create')
            ->name('payroll.store');
        Route::delete('payroll/{payroll_run}', [PayrollController::class, 'destroy'])
            ->middleware('permission:payroll.delete')
            ->name('payroll.destroy');
        Route::post('payroll/{payroll_run}/employees/{employee}/adjustments', [PayrollController::class, 'storeAdjustment'])
            ->middleware('permission:payroll.adjust')
            ->name('payroll.employees.adjustments.store');
        Route::post('payroll/{payroll_run}/adjustments/bulk', [PayrollController::class, 'storeBulkAdjustment'])
            ->middleware('permission:payroll.adjust')
            ->name('payroll.adjustments.bulk');
        Route::delete('payroll/{payroll_run}/adjustments/{adjustment}', [PayrollController::class, 'destroyAdjustment'])
            ->middleware('permission:payroll.adjust')
            ->name('payroll.adjustments.destroy');
        Route::post('payroll/{payroll_run}/process', [PayrollController::class, 'process'])
            ->middleware('permission:payroll.process')
            ->name('payroll.process');
        Route::post('payroll/{payroll_run}/rerun', [PayrollController::class, 'rerun'])
            ->middleware('permission:payroll.process')
            ->name('payroll.rerun');
        Route::post('payroll/{payroll_run}/finalize', [PayrollController::class, 'finalize'])
            ->middleware('permission:payroll.finalize')
            ->name('payroll.finalize');
        Route::post('payroll/{payroll_run}/reopen', [PayrollController::class, 'reopen'])
            ->middleware('permission:payroll.finalize')
            ->name('payroll.reopen');
        Route::get('payroll/{payroll_run}/export', [PayrollController::class, 'export'])
            ->middleware('permission:payroll.export')
            ->name('payroll.export');
    });

    Route::middleware('permission:app-settings.view')->group(function () {
        Route::get('app-settings', [AppSettingController::class, 'index'])->name('app-settings.index');
        Route::post('app-settings', [AppSettingController::class, 'update'])
            ->middleware('permission:app-settings.update')
            ->name('app-settings.update');
    });

    Route::middleware('permission:attendance-settings.view')->group(function () {
        Route::get('attendance-settings', [AttendanceSettingController::class, 'index'])->name('attendance-settings.index');
        Route::put('attendance-settings/payroll-period', [AttendanceSettingController::class, 'updatePayrollPeriod'])
            ->middleware('permission:attendance-settings.payroll-period.update')
            ->name('attendance-settings.payroll-period.update');
        Route::put('attendance-settings/leave-carry-forward', [AttendanceSettingController::class, 'updateLeaveCarryForward'])
            ->middleware('permission:attendance-settings.leave-carry-forward.update')
            ->name('attendance-settings.leave-carry-forward.update');
        Route::put('attendance-settings/leave-approval-workflow', [AttendanceSettingController::class, 'updateLeaveApprovalWorkflow'])
            ->middleware('permission:attendance-settings.leave-workflow.update')
            ->name('attendance-settings.leave-workflow.update');
        Route::post('attendance-settings/approval-templates', [AttendanceSettingController::class, 'storeApprovalTemplate'])
            ->middleware('permission:attendance-settings.leave-workflow.update')
            ->name('attendance-settings.approval-templates.store');
        Route::put('attendance-settings/approval-templates/{approvalTemplate}', [AttendanceSettingController::class, 'updateApprovalTemplate'])
            ->middleware('permission:attendance-settings.leave-workflow.update')
            ->name('attendance-settings.approval-templates.update');
        Route::delete('attendance-settings/approval-templates/{approvalTemplate}', [AttendanceSettingController::class, 'destroyApprovalTemplate'])
            ->middleware('permission:attendance-settings.leave-workflow.update')
            ->name('attendance-settings.approval-templates.destroy');
        Route::put('attendance-settings/approval-template-defaults', [AttendanceSettingController::class, 'updateCompanyDefaultApprovalTemplates'])
            ->middleware('permission:attendance-settings.leave-workflow.update')
            ->name('attendance-settings.approval-template-defaults.update');
        Route::post('attendance-settings/banks', [BankController::class, 'store'])
            ->middleware('permission:attendance-settings.payroll-period.update')
            ->name('attendance-settings.banks.store');
        Route::put('attendance-settings/banks/{bank}', [BankController::class, 'update'])
            ->middleware('permission:attendance-settings.payroll-period.update')
            ->name('attendance-settings.banks.update');
        Route::delete('attendance-settings/banks/{bank}', [BankController::class, 'destroy'])
            ->middleware('permission:attendance-settings.payroll-period.update')
            ->name('attendance-settings.banks.destroy');
        Route::post('attendance-settings/duty-policies', [AttendanceSettingController::class, 'storePolicy'])
            ->middleware('permission:attendance-settings.duty-policies.create')
            ->name('attendance-settings.duty-policies.store');
        Route::put('attendance-settings/duty-policies/{attendance_duty_policy}', [AttendanceSettingController::class, 'updatePolicy'])
            ->middleware('permission:attendance-settings.duty-policies.update')
            ->name('attendance-settings.duty-policies.update');
        Route::delete('attendance-settings/duty-policies/{attendance_duty_policy}', [AttendanceSettingController::class, 'destroyPolicy'])
            ->middleware('permission:attendance-settings.duty-policies.delete')
            ->name('attendance-settings.duty-policies.destroy');
        Route::post('attendance-settings/holidays', [AttendanceSettingController::class, 'storeHoliday'])
            ->middleware('permission:attendance-settings.holidays.create')
            ->name('attendance-settings.holidays.store');
        Route::put('attendance-settings/holidays/{public_holiday}', [AttendanceSettingController::class, 'updateHoliday'])
            ->middleware('permission:attendance-settings.holidays.update')
            ->name('attendance-settings.holidays.update');
        Route::delete('attendance-settings/holidays/{public_holiday}', [AttendanceSettingController::class, 'destroyHoliday'])
            ->middleware('permission:attendance-settings.holidays.delete')
            ->name('attendance-settings.holidays.destroy');
    });

    Route::redirect('attendance-settings/edit', '/attendance-settings');
    Route::redirect('public-holidays', '/attendance-settings');
    Route::redirect('public-holidays/create', '/attendance-settings');

    Route::get('zkt-attendance-logs', [ZktAttendanceLogController::class, 'index'])
        ->middleware('permission:zkt-attendance-logs.view')
        ->name('zkt-attendance-logs.index');

    Route::get('mobile-punch-logs', [MobilePunchAccessLogController::class, 'index'])
        ->middleware('permission:mobile-punch-logs.view')
        ->name('mobile-punch-logs.index');

    Route::middleware('permission:roles.view')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])
            ->middleware('permission:roles.create')
            ->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.create')
            ->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:roles.update')
            ->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.update')
            ->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.delete')
            ->name('roles.destroy');
    });
});
