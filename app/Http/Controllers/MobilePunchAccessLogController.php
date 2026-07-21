<?php

namespace App\Http\Controllers;

use App\Services\Attendance\MobilePunchAccessLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MobilePunchAccessLogController extends Controller
{
    public function index(Request $request, MobilePunchAccessLogService $accessLogService): Response
    {
        $tab = $request->string('tab')->toString();
        $activeTab = in_array($tab, ['punches', 'doors'], true) ? $tab : 'punches';

        return Inertia::render('MobilePunchAccessLogs/Index', [
            'activeTab' => $activeTab,
            'punchLogs' => $accessLogService->punchLogs($request),
            'doorLogs' => $accessLogService->doorOpenLogs($request),
            'punchSites' => $accessLogService->punchSiteOptions(),
            'doorSites' => $accessLogService->doorSiteOptions(),
            'filters' => [
                'search' => $request->input('search'),
                'site_id' => $request->input('site_id'),
                'client_device_id' => $request->input('client_device_id'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
        ]);
    }
}
