<?php

namespace App\Http\Controllers;

use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'devices' => ZktDevice::query()->count(),
                'activeDevices' => ZktDevice::query()->where('is_active', true)->count(),
                'onlineDevices' => ZktDevice::query()->where('connection_status', 'online')->count(),
                'punchesToday' => ZktAttendanceLog::query()->whereDate('punched_at', today())->count(),
            ],
        ]);
    }
}
