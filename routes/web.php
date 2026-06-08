<?php

use App\Http\Controllers\AttendanceSettingController;
use App\Http\Controllers\AttendanceSheetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
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
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::post('zkt-devices/probe', [ZktDeviceController::class, 'probe'])->name('zkt-devices.probe');
    Route::post('zkt-devices/sync-all', [ZktDeviceController::class, 'syncAll'])->name('zkt-devices.sync-all');
    Route::post('zkt-devices/{zkt_device}/test', [ZktDeviceController::class, 'test'])->name('zkt-devices.test');
    Route::post('zkt-devices/{zkt_device}/sync', [ZktDeviceController::class, 'sync'])->name('zkt-devices.sync');
    Route::post('zkt-devices/{zkt_device}/read-time', [ZktDeviceController::class, 'readTime'])->name('zkt-devices.read-time');
    Route::post('zkt-devices/{zkt_device}/sync-time', [ZktDeviceController::class, 'syncTime'])->name('zkt-devices.sync-time');

    Route::resource('zkt-devices', ZktDeviceController::class);

    Route::resource('employees', EmployeeController::class);
    Route::resource('departments', DepartmentController::class);

    Route::get('attendance-sheet', [AttendanceSheetController::class, 'index'])->name('attendance-sheet.index');
    Route::get('attendance-settings', [AttendanceSettingController::class, 'index'])->name('attendance-settings.index');
    Route::post('attendance-settings/duty-policies', [AttendanceSettingController::class, 'storePolicy'])->name('attendance-settings.duty-policies.store');
    Route::put('attendance-settings/duty-policies/{attendance_duty_policy}', [AttendanceSettingController::class, 'updatePolicy'])->name('attendance-settings.duty-policies.update');
    Route::delete('attendance-settings/duty-policies/{attendance_duty_policy}', [AttendanceSettingController::class, 'destroyPolicy'])->name('attendance-settings.duty-policies.destroy');
    Route::post('attendance-settings/holidays', [AttendanceSettingController::class, 'storeHoliday'])->name('attendance-settings.holidays.store');
    Route::put('attendance-settings/holidays/{public_holiday}', [AttendanceSettingController::class, 'updateHoliday'])->name('attendance-settings.holidays.update');
    Route::delete('attendance-settings/holidays/{public_holiday}', [AttendanceSettingController::class, 'destroyHoliday'])->name('attendance-settings.holidays.destroy');
    Route::redirect('attendance-settings/edit', '/attendance-settings');
    Route::redirect('public-holidays', '/attendance-settings');
    Route::redirect('public-holidays/create', '/attendance-settings');

    Route::get('zkt-attendance-logs', [ZktAttendanceLogController::class, 'index'])
        ->name('zkt-attendance-logs.index');
});
