<?php

use App\Http\Controllers\AttendanceSettingController;
use App\Http\Controllers\AttendanceSheetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\PayrollStructureController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ZktAttendanceLogController;
use App\Http\Controllers\ZktDeviceController;
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
        Route::resource('employees', EmployeeController::class)->middleware([
            'index' => 'permission:employees.view',
            'show' => 'permission:employees.view',
            'create' => 'permission:employees.create',
            'store' => 'permission:employees.create',
            'edit' => 'permission:employees.update',
            'update' => 'permission:employees.update',
            'destroy' => 'permission:employees.delete',
        ]);
    });

    Route::middleware('permission:departments.view')->group(function () {
        Route::resource('departments', DepartmentController::class)->middleware([
            'index' => 'permission:departments.view',
            'show' => 'permission:departments.view',
            'create' => 'permission:departments.create',
            'store' => 'permission:departments.create',
            'edit' => 'permission:departments.update',
            'update' => 'permission:departments.update',
            'destroy' => 'permission:departments.delete',
        ]);
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

    Route::get('attendance-sheet', [AttendanceSheetController::class, 'index'])
        ->middleware('permission:attendance-sheet.view')
        ->name('attendance-sheet.index');

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

    Route::middleware('permission:leave-balances.view')->group(function () {
        Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
        Route::get('leave-balances/export-all', [LeaveBalanceController::class, 'exportAll'])->name('leave-balances.export-all');
        Route::get('leave-balances/export', [LeaveBalanceController::class, 'export'])->name('leave-balances.export');
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
        Route::post('payroll-structure/components', [PayrollStructureController::class, 'storeComponent'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.components.store');
        Route::put('payroll-structure/components/{payroll_component}', [PayrollStructureController::class, 'updateComponent'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.components.update');
        Route::delete('payroll-structure/components/{payroll_component}', [PayrollStructureController::class, 'destroyComponent'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.components.destroy');
        Route::post('payroll-structure/designations', [PayrollStructureController::class, 'storeDesignation'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.designations.store');
        Route::put('payroll-structure/designations/{designation}', [PayrollStructureController::class, 'updateDesignation'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.designations.update');
        Route::delete('payroll-structure/designations/{designation}', [PayrollStructureController::class, 'destroyDesignation'])
            ->middleware('permission:payroll-structure.update')
            ->name('payroll-structure.designations.destroy');
    });

    Route::middleware('permission:attendance-settings.view')->group(function () {
        Route::get('attendance-settings', [AttendanceSettingController::class, 'index'])->name('attendance-settings.index');
        Route::put('attendance-settings/payroll-period', [AttendanceSettingController::class, 'updatePayrollPeriod'])
            ->middleware('permission:attendance-settings.payroll-period.update')
            ->name('attendance-settings.payroll-period.update');
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
