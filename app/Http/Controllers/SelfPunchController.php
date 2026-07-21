<?php

namespace App\Http\Controllers;

use App\Http\Requests\OpenRemoteDoorRequest;
use App\Http\Requests\StoreSelfPunchRequest;
use App\Models\RemoteDoorSite;
use App\Models\SelfPunchSite;
use App\Services\Access\RemoteDoorService;
use App\Services\Attendance\SelfPunchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SelfPunchController extends Controller
{
    public function index(Request $request, SelfPunchService $selfPunchService, RemoteDoorService $remoteDoorService): Response
    {
        $user = $request->user();
        $employee = $user?->employee;

        $clientIp = $request->ip();

        $sites = $employee
            ? SelfPunchSite::activeSitesForEmployee($employee)
                ->map(fn (SelfPunchSite $site) => $site->toEmployeeFacingArray($clientIp))
                ->values()
                ->all()
            : [];

        $doorSites = $employee
            ? RemoteDoorSite::activeSitesForEmployee($employee)
                ->map(fn (RemoteDoorSite $site) => $site->toEmployeeFacingArray($clientIp))
                ->values()
                ->all()
            : [];

        return Inertia::render('SelfPunch/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->name,
                'staff_id' => $employee->staff_id,
            ] : null,
            'sites' => $sites,
            'doorSites' => $doorSites,
            'todaysPunches' => $employee ? $selfPunchService->todaysPunches($employee) : [],
            'todaysDoorOpens' => $employee ? $remoteDoorService->todaysOpens($employee) : [],
            'doorOpenCooldownSeconds' => (int) config('zkt.door_open_cooldown_seconds', 10),
            'doorOpenCooldownRemaining' => $employee ? $remoteDoorService->doorOpenCooldownRemaining($employee) : 0,
            'clientIp' => $clientIp,
        ]);
    }

    public function store(StoreSelfPunchRequest $request, SelfPunchService $selfPunchService): RedirectResponse
    {
        $log = $selfPunchService->punch(
            $request->user(),
            $request->integer('self_punch_site_id'),
            $request->integer('punch_state'),
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            $request->filled('accuracy_meters') ? (float) $request->input('accuracy_meters') : null,
            $request->ip(),
            $request->string('device_id')->toString(),
            $request->input('public_ip'),
        );

        $label = $log->punchStateLabel();

        return back()->with('success', "{$label} recorded at ".now()->format('H:i:s').'.');
    }

    public function openDoor(OpenRemoteDoorRequest $request, RemoteDoorService $remoteDoorService): RedirectResponse
    {
        $log = $remoteDoorService->openDoor(
            $request->user(),
            $request->integer('remote_door_site_id'),
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            $request->filled('accuracy_meters') ? (float) $request->input('accuracy_meters') : null,
            $request->ip(),
            $request->input('public_ip'),
        );

        $message = $log->result_message ?? 'Door unlock requested at '.now()->format('H:i:s').'.';

        return back()->with('success', $message);
    }
}
