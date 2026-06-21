<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreZktLocationGroupRequest;
use App\Http\Requests\UpdateZktLocationGroupRequest;
use App\Models\Employee;
use App\Models\ZktDevice;
use App\Models\ZktLocationGroup;
use App\Services\Zkt\ZktDeviceUserSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ZktLocationGroupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ZktLocationGroups/Index', [
            'locationGroups' => ZktLocationGroup::query()
                ->withCount(['devices', 'employees'])
                ->with(['devices:id,name,location,is_active,machine_type'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (ZktLocationGroup $group) => $group->toPresentationArray()),
            'devices' => ZktDevice::query()
                ->where('is_active', true)
                ->where('ip_address', '!=', '0.0.0.0')
                ->orderBy('name')
                ->get(['id', 'name', 'location', 'machine_type'])
                ->map(fn (ZktDevice $device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'location' => $device->location,
                    'machine_type' => $device->machine_type->value,
                    'machine_type_label' => $device->machine_type->shortLabel(),
                ]),
            'emptyLocationGroup' => $this->emptyLocationGroup(),
        ]);
    }

    public function store(StoreZktLocationGroupRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('device_ids');
        $group = ZktLocationGroup::query()->create($data);
        $group->devices()->sync($request->input('device_ids', []));

        return back()->with('success', 'Location group created successfully.');
    }

    public function update(UpdateZktLocationGroupRequest $request, ZktLocationGroup $zktLocationGroup, ZktDeviceUserSyncService $syncService): RedirectResponse
    {
        $data = $request->safe()->except('device_ids');
        $zktLocationGroup->update($data);
        $zktLocationGroup->devices()->sync($request->input('device_ids', []));

        $syncService->syncLocationGroup($zktLocationGroup->fresh());

        return back()->with('success', 'Location group updated and machine access synced.');
    }

    public function destroy(ZktLocationGroup $zktLocationGroup, ZktDeviceUserSyncService $syncService): RedirectResponse
    {
        $employeeIds = $zktLocationGroup->eligibleEmployeeIds();
        $employees = Employee::query()->whereIn('id', $employeeIds)->get();

        $zktLocationGroup->employees()->detach();
        $zktLocationGroup->devices()->detach();
        $zktLocationGroup->delete();

        foreach ($employees as $employee) {
            $syncService->syncEmployee($employee->fresh(['zktLocationGroups', 'zktDeviceSyncs.device']));
        }

        return back()->with('success', 'Location group deleted and employee device access updated.');
    }

    public function syncUsers(ZktLocationGroup $zktLocationGroup, ZktDeviceUserSyncService $syncService): RedirectResponse
    {
        $results = $syncService->syncLocationGroup($zktLocationGroup);
        $failed = collect($results)->where('status', 'failed')->count();
        $synced = collect($results)->where('status', 'synced')->count();

        if ($failed > 0) {
            return back()->with(
                'error',
                "Synced {$synced} device profile(s), but {$failed} failed. Check employee device sync status for details.",
            );
        }

        return back()->with('success', "Synced {$synced} employee device profile(s) for this location group.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyLocationGroup(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'description' => '',
            'sort_order' => 0,
            'is_active' => true,
            'device_ids' => [],
        ];
    }
}
