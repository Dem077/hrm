<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceMachineBrand;
use App\Enums\ZktConnectionProtocol;
use App\Enums\ZktConnectionStatus;
use App\Enums\ZktMachineType;
use App\Http\Requests\ProbeZktDeviceRequest;
use App\Http\Requests\StoreZktDeviceRequest;
use App\Http\Requests\UpdateZktDeviceRequest;
use App\Jobs\SyncZktDeviceJob;
use App\Models\ZktDevice;
use App\Services\Zkt\ZktDeviceClient;
use App\Services\Zkt\ZktDeviceSyncService;
use App\Services\Zkt\ZktDeviceUserSyncService;
use App\Support\DateFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ZktDeviceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ZktDevices/Index', [
            'devices' => ZktDevice::query()
                ->latest()
                ->get()
                ->map(fn (ZktDevice $device) => $this->formatDevice($device)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ZktDevices/Form', [
            'device' => $this->emptyDevice(),
            'protocols' => $this->protocolOptions(),
            'brands' => AttendanceMachineBrand::options(),
            'machineTypes' => ZktMachineType::options(),
        ]);
    }

    public function store(StoreZktDeviceRequest $request): RedirectResponse
    {
        ZktDevice::query()->create($request->validated());

        return redirect()
            ->route('zkt-devices.index')
            ->with('success', 'Attendance machine created successfully.');
    }

    public function show(ZktDevice $zktDevice): Response
    {
        $zktDevice->load([
            'syncLogs' => fn ($query) => $query->latest('started_at')->limit(20),
            'attendanceLogs' => fn ($query) => $query->with('employee:id,staff_id,name')->latest('punched_at')->limit(50),
            'employeeSyncs.employee:id,staff_id,name',
        ]);

        return Inertia::render('ZktDevices/Show', [
            'device' => $this->formatDevice($zktDevice, includeRelations: true),
        ]);
    }

    public function edit(ZktDevice $zktDevice): Response
    {
        return Inertia::render('ZktDevices/Form', [
            'device' => $this->formatDevice($zktDevice),
            'protocols' => $this->protocolOptions(),
            'brands' => AttendanceMachineBrand::options(),
            'machineTypes' => ZktMachineType::options(),
        ]);
    }

    public function update(UpdateZktDeviceRequest $request, ZktDevice $zktDevice): RedirectResponse
    {
        $zktDevice->update($request->validated());

        return redirect()
            ->route('zkt-devices.show', $zktDevice)
            ->with('success', 'Attendance machine updated successfully.');
    }

    public function destroy(ZktDevice $zktDevice): RedirectResponse
    {
        $zktDevice->delete();

        return redirect()
            ->route('zkt-devices.index')
            ->with('success', 'Attendance machine deleted successfully.');
    }

    public function probe(ProbeZktDeviceRequest $request, ZktDeviceClient $client): JsonResponse
    {
        $device = new ZktDevice($request->validated());
        $result = $client->probe($device);

        if ($result['connected']) {
            return response()->json([
                'connected' => true,
                'message' => $result['message'],
                'device' => [
                    'serial_number' => $result['device_info']['serial_number'],
                    'model_name' => $result['device_info']['model_name'],
                    'firmware_version' => $result['device_info']['firmware_version'],
                    'connection_status' => ZktConnectionStatus::Online->value,
                    'connection_status_label' => ZktConnectionStatus::Online->label(),
                    'last_connected_at' => now()->toIso8601String(),
                    'last_sync_error' => null,
                ],
            ]);
        }

        return response()->json([
            'connected' => false,
            'message' => $result['message'],
            'device' => [
                'connection_status' => ZktConnectionStatus::Offline->value,
                'connection_status_label' => ZktConnectionStatus::Offline->label(),
                'last_sync_error' => $result['message'],
            ],
        ], 422);
    }

    public function test(ZktDevice $zktDevice, ZktDeviceClient $client): RedirectResponse
    {
        if ($redirect = $this->ensureDeviceIsActive($zktDevice)) {
            return $redirect;
        }

        $result = $client->testConnection($zktDevice);

        return back()->with(
            $result['connected'] ? 'success' : 'error',
            $result['message'],
        );
    }

    public function sync(ZktDevice $zktDevice, ZktDeviceSyncService $syncService): RedirectResponse
    {
        if ($redirect = $this->ensureDeviceIsActive($zktDevice)) {
            return $redirect;
        }

        $syncLog = $syncService->sync($zktDevice);

        return back()->with(
            $syncLog->status->value === 'success' ? 'success' : 'error',
            $syncLog->message ?? 'Sync completed.',
        );
    }

    public function readTime(ZktDevice $zktDevice, ZktDeviceClient $client): RedirectResponse
    {
        if ($redirect = $this->ensureDeviceIsActive($zktDevice)) {
            return $redirect;
        }

        try {
            $result = $client->readDeviceTime($zktDevice);

            $deviceTime = $result['device_time']
                ? DateFormatter::formatDateTime($result['device_time'])
                : '—';
            $serverTime = DateFormatter::formatDateTime($result['server_time']);

            $message = "Device: {$deviceTime} · Server: {$serverTime}";

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            if ($zktDevice->exists) {
                $zktDevice->update([
                    'connection_status' => ZktConnectionStatus::Offline,
                    'last_sync_error' => $exception->getMessage(),
                ]);
            }

            return back()->with('error', $exception->getMessage());
        }
    }

    public function syncTime(ZktDevice $zktDevice, ZktDeviceClient $client): RedirectResponse
    {
        if ($redirect = $this->ensureDeviceIsActive($zktDevice)) {
            return $redirect;
        }

        try {
            $result = $client->syncDeviceTime($zktDevice);

            $displayTime = $result['device_time_after']
                ? DateFormatter::formatDateTime($result['device_time_after'])
                : DateFormatter::formatDateTime(now());

            $message = 'Device clock updated to '.$displayTime.'.';

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            if ($zktDevice->exists) {
                $zktDevice->update([
                    'connection_status' => ZktConnectionStatus::Offline,
                    'last_sync_error' => $exception->getMessage(),
                ]);
            }

            return back()->with('error', $exception->getMessage());
        }
    }

    public function syncAll(Request $request): RedirectResponse
    {
        $devices = ZktDevice::query()->where('is_active', true)->get();

        foreach ($devices as $device) {
            SyncZktDeviceJob::dispatch($device);
        }

        return back()->with('success', "Queued {$devices->count()} device(s) for sync.");
    }

    public function syncUsers(ZktDevice $zktDevice, ZktDeviceUserSyncService $userSyncService): RedirectResponse
    {
        if ($redirect = $this->ensureDeviceIsActive($zktDevice)) {
            return $redirect;
        }

        if (! $zktDevice->isManagedDevice()) {
            return back()->with('error', 'User profiles cannot be managed on this device.');
        }

        $results = $userSyncService->syncDeviceUsers($zktDevice);
        $failed = collect($results)->where('status', 'failed')->count();
        $synced = collect($results)->where('status', 'synced')->count();

        if ($failed > 0) {
            return back()->with(
                'error',
                "Synced {$synced} user profile(s), but {$failed} failed. Review sync status below.",
            );
        }

        return back()->with('success', "Synced {$synced} assigned user profile(s) to this machine.");
    }

    public function pullUsers(ZktDevice $zktDevice, ZktDeviceUserSyncService $userSyncService): RedirectResponse
    {
        if ($redirect = $this->ensureDeviceIsActive($zktDevice)) {
            return $redirect;
        }

        if (! $zktDevice->isManagedDevice()) {
            return back()->with('error', 'User profiles cannot be read from this device.');
        }

        try {
            $results = $userSyncService->pullDeviceCredentials($zktDevice);
            $updated = collect($results)->where('status', 'updated')->count();
            $matched = count($results);

            if ($matched === 0) {
                return back()->with(
                    'error',
                    'No matching employees were found on this machine. Users must use the same staff ID in HRM.',
                );
            }

            if ($updated === 0) {
                return back()->with(
                    'success',
                    "Found {$matched} matching employee(s), but none had a card number, password, or privilege to import.",
                );
            }

            return back()->with(
                'success',
                "Imported card number, password, and/or privilege for {$updated} employee(s) from this machine.",
            );
        } catch (Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatDevice(ZktDevice $device, bool $includeRelations = false): array
    {
        $data = [
            'id' => $device->id,
            'name' => $device->name,
            'brand' => $device->brand->value,
            'brand_label' => $device->brand->label(),
            'location' => $device->location,
            'machine_type' => $device->machine_type->value,
            'machine_type_label' => $device->machine_type->label(),
            'machine_type_short_label' => $device->machine_type->shortLabel(),
            'machine_type_color' => $device->machine_type->color(),
            'ip_address' => $device->ip_address,
            'port' => $device->port,
            'protocol' => $device->protocol->value,
            'protocol_label' => $device->protocol->label(),
            'comm_password' => $device->comm_password,
            'serial_number' => $device->serial_number,
            'model_name' => $device->model_name,
            'firmware_version' => $device->firmware_version,
            'is_active' => $device->is_active,
            'auto_sync' => $device->auto_sync,
            'sync_interval_minutes' => $device->sync_interval_minutes,
            ...$device->connectionStatusPresentation(),
            'last_connected_at' => $device->last_connected_at?->toIso8601String(),
            'last_synced_at' => $device->last_synced_at?->toIso8601String(),
            'last_sync_error' => $device->last_sync_error,
            'notes' => $device->notes,
            'tcpmux_enabled' => $device->tcpmux_enabled,
            'tcpmux_subdomain' => $device->tcpmux_subdomain,
            'tcpmux_port' => $device->tcpmux_port,
            'created_at' => $device->created_at?->toIso8601String(),
            'updated_at' => $device->updated_at?->toIso8601String(),
        ];

        if ($includeRelations) {
            $data['sync_logs'] = $device->syncLogs->map(fn ($log) => [
                'id' => $log->id,
                'status' => $log->status->value,
                'status_label' => $log->status->label(),
                'status_color' => $log->status->color(),
                'records_fetched' => $log->records_fetched,
                'records_stored' => $log->records_stored,
                'message' => $log->message,
                'started_at' => $log->started_at?->toIso8601String(),
                'completed_at' => $log->completed_at?->toIso8601String(),
            ]);
            $data['attendance_logs'] = $device->attendanceLogs->map(
                fn ($log) => $log->toPresentationArray(),
            );
            $data['employee_syncs'] = $device->employeeSyncs->map(
                fn ($sync) => [
                    ...$sync->toPresentationArray(),
                    'employee' => $sync->employee ? [
                        'id' => $sync->employee->id,
                        'name' => $sync->employee->name,
                        'staff_id' => $sync->employee->staff_id,
                    ] : null,
                ],
            );
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyDevice(): array
    {
        return [
            'id' => null,
            'name' => '',
            'brand' => AttendanceMachineBrand::Zkt->value,
            'location' => '',
            'machine_type' => ZktMachineType::Attendance->value,
            'ip_address' => '',
            'port' => (int) config('zkt.default_port', 4370),
            'protocol' => config('zkt.default_protocol', 'tcp'),
            'comm_password' => 0,
            'serial_number' => null,
            'model_name' => null,
            'firmware_version' => null,
            'is_active' => true,
            'auto_sync' => true,
            'sync_interval_minutes' => (int) config('zkt.sync_interval_minutes', 10),
            'connection_status' => ZktConnectionStatus::Unknown->value,
            'connection_status_label' => ZktConnectionStatus::Unknown->label(),
            'connection_status_color' => ZktConnectionStatus::Unknown->color(),
            'last_connected_at' => null,
            'last_synced_at' => null,
            'last_sync_error' => null,
            'notes' => '',
            'tcpmux_enabled' => false,
            'tcpmux_subdomain' => null,
            'tcpmux_port' => null,
        ];
    }

    protected function ensureDeviceIsActive(ZktDevice $device): ?RedirectResponse
    {
        if ($device->is_active) {
            return null;
        }

        return back()->with('error', 'This machine is inactive.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function protocolOptions(): array
    {
        return collect(ZktConnectionProtocol::cases())
            ->map(fn ($protocol) => [
                'value' => $protocol->value,
                'label' => $protocol->label(),
            ])
            ->values()
            ->all();
    }
}
