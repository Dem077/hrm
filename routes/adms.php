<?php

use App\Http\Controllers\Adms\IclockController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], 'iclock/cdata', [IclockController::class, 'cdata'])->name('adms.cdata');
Route::get('iclock/getrequest', [IclockController::class, 'getrequest'])->name('adms.getrequest');
Route::post('iclock/devicecmd', [IclockController::class, 'devicecmd'])->name('adms.devicecmd');
Route::match(['GET', 'POST'], 'iclock/registry', [IclockController::class, 'registry'])->name('adms.registry');
