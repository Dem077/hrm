<?php

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

    Route::get('zkt-attendance-logs', [ZktAttendanceLogController::class, 'index'])
        ->name('zkt-attendance-logs.index');
});
