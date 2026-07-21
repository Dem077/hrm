<?php

namespace App\Http\Controllers;

use App\Enums\ZktMachineType;
use App\Http\Requests\StoreSelfPunchSiteRequest;
use App\Http\Requests\UpdateSelfPunchSiteRequest;
use App\Models\Employee;
use App\Models\RemoteDoorSite;
use App\Models\SelfPunchSite;
use App\Models\ZktDevice;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SelfPunchSiteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SelfPunchSites/Index', [
            'sites' => SelfPunchSite::query()
                ->withCount('employees')
                ->with(['employees:id,name,staff_id'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (SelfPunchSite $site) => $site->toPresentationArray()),
            'doorSites' => RemoteDoorSite::query()
                ->withCount('employees')
                ->with(['employees:id,name,staff_id', 'device:id,name'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (RemoteDoorSite $site) => $site->toPresentationArray()),
            'accessDevices' => ZktDevice::query()
                ->excludeSystemDevices()
                ->where('machine_type', ZktMachineType::Access->value)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'connection_mode', 'serial_number'])
                ->map(fn (ZktDevice $device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'connection_mode' => $device->connection_mode->value,
                    'serial_number' => $device->serial_number,
                ]),
            'employees' => Employee::query()
                ->where('is_active', true)
                ->whereNotNull('user_id')
                ->orderBy('name')
                ->get(['id', 'name', 'staff_id'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'staff_id' => $employee->staff_id,
                ]),
            'emptySite' => $this->emptySite(),
            'emptyDoorSite' => $this->emptyDoorSite(),
        ]);
    }

    public function store(StoreSelfPunchSiteRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('employee_ids');
        $site = SelfPunchSite::query()->create($data);
        $site->employees()->sync($request->input('employee_ids', []));

        return back()->with('success', 'Mobile punch site created successfully.');
    }

    public function update(UpdateSelfPunchSiteRequest $request, SelfPunchSite $selfPunchSite): RedirectResponse
    {
        $data = $request->safe()->except('employee_ids');
        $selfPunchSite->update($data);
        $selfPunchSite->employees()->sync($request->input('employee_ids', []));

        return back()->with('success', 'Mobile punch site updated successfully.');
    }

    public function destroy(SelfPunchSite $selfPunchSite): RedirectResponse
    {
        $selfPunchSite->employees()->detach();
        $selfPunchSite->delete();

        return back()->with('success', 'Mobile punch site deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptySite(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'description' => '',
            'latitude' => null,
            'longitude' => null,
            'radius_meters' => 150,
            'max_accuracy_meters' => 250,
            'allowed_public_ips' => [],
            'allowed_public_ips_text' => '',
            'require_public_ip' => false,
            'sort_order' => 0,
            'is_active' => true,
            'employee_ids' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyDoorSite(): array
    {
        return [
            'id' => null,
            'zkt_device_id' => null,
            'name' => '',
            'code' => '',
            'description' => '',
            'latitude' => null,
            'longitude' => null,
            'radius_meters' => 150,
            'max_accuracy_meters' => 250,
            'allowed_public_ips' => [],
            'allowed_public_ips_text' => '',
            'require_public_ip' => false,
            'sort_order' => 0,
            'is_active' => true,
            'employee_ids' => [],
        ];
    }
}
