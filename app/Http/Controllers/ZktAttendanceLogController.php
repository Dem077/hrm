<?php

namespace App\Http\Controllers;

use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ZktAttendanceLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = ZktAttendanceLog::query()
            ->forPunchLog()
            ->with('device:id,name')
            ->with('employee:id,staff_id,name')
            ->when($request->filled('device_id'), fn ($query) => $query->where('zkt_device_id', $request->integer('device_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($inner) use ($search) {
                    $inner->where('device_user_id', 'like', "%{$search}%")
                        ->orWhereHas('device', fn ($deviceQuery) => $deviceQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                            $employeeQuery->where('staff_id', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('punched_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ZktAttendanceLog $log) => [
                ...$log->toPresentationArray(),
                'device' => [
                    'id' => $log->device->id,
                    'name' => $log->device->name,
                ],
            ]);

        return Inertia::render('ZktAttendanceLogs/Index', [
            'logs' => $logs,
            'devices' => ZktDevice::query()
                ->excludeSystemDevices()
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'device_id' => $request->input('device_id'),
                'search' => $request->input('search'),
            ],
        ]);
    }
}
