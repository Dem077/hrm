<?php

namespace App\Services\Access;

use App\Enums\ZktMachineType;
use App\Models\Employee;
use App\Models\RemoteDoorOpenLog;
use App\Models\RemoteDoorSite;
use App\Models\User;
use App\Services\Adms\AdmsCommandQueue;
use App\Services\Adms\AdmsDoorCommandBuilder;
use App\Services\Zkt\ZktDeviceClient;
use App\Services\Zkt\ZktDeviceException;
use App\Support\PublicIp;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RemoteDoorService
{
    public function __construct(
        protected AdmsCommandQueue $commandQueue,
        protected AdmsDoorCommandBuilder $doorCommandBuilder,
        protected ZktDeviceClient $deviceClient,
    ) {}

    public function openDoor(
        User $user,
        int $siteId,
        float $latitude,
        float $longitude,
        ?float $accuracyMeters,
        ?string $requestIp,
        ?string $reportedPublicIp = null,
    ): RemoteDoorOpenLog {
        $employee = $user->employee;

        if (! $employee || ! $employee->is_active) {
            throw ValidationException::withMessages([
                'employee' => 'Your login is not linked to an active employee profile.',
            ]);
        }

        $site = RemoteDoorSite::query()
            ->with('device')
            ->whereKey($siteId)
            ->where('is_active', true)
            ->whereHas('employees', fn ($query) => $query->where('employees.id', $employee->id))
            ->first();

        if (! $site) {
            throw ValidationException::withMessages([
                'remote_door_site_id' => 'You are not assigned to this door site.',
            ]);
        }

        $device = $site->device;

        if (! $device || ! $device->is_active || ! $device->isManagedDevice()) {
            throw ValidationException::withMessages([
                'device' => 'The access machine for this site is not available.',
            ]);
        }

        if ($device->machine_type !== ZktMachineType::Access) {
            throw ValidationException::withMessages([
                'device' => 'This site is not linked to an access machine.',
            ]);
        }

        $effectiveIp = PublicIp::resolve($requestIp, $reportedPublicIp);

        if (! $site->clientIpIsAllowed($effectiveIp)) {
            throw ValidationException::withMessages([
                'network' => 'You must be connected to the office Wi‑Fi.',
            ]);
        }

        if ($accuracyMeters !== null && $accuracyMeters > $site->max_accuracy_meters) {
            throw ValidationException::withMessages([
                'accuracy_meters' => 'GPS accuracy is too low ('.$accuracyMeters.'m). Move outdoors or wait for a better signal (max '.$site->max_accuracy_meters.'m).',
            ]);
        }

        if (! $site->containsCoordinates($latitude, $longitude, $accuracyMeters)) {
            $distance = (int) round($site->distanceMetersFrom($latitude, $longitude));

            throw ValidationException::withMessages([
                'location' => "You are about {$distance}m from {$site->name}. Move within {$site->radius_meters}m of the site to open the door.",
            ]);
        }

        $this->assertCooldown($employee);

        $command = null;
        $status = 'queued';
        $resultMessage = null;

        if ($device->usesAdms()) {
            if (! filled($device->serial_number)) {
                throw ValidationException::withMessages([
                    'device' => 'This access machine has no serial number configured for ADMS.',
                ]);
            }

            $command = $this->commandQueue->enqueue($device, $this->doorCommandBuilder->unlockPayload());
            $status = 'queued';
            $resultMessage = 'Unlock command queued. The door will open when the machine polls the server (usually within a minute).';
        } elseif ($device->usesTcpPull()) {
            try {
                $this->deviceClient->unlockDoor($device);
            } catch (ZktDeviceException $exception) {
                throw ValidationException::withMessages([
                    'device' => $exception->getMessage(),
                ]);
            }

            $status = 'opened';
            $resultMessage = 'Door unlock sent to the access machine.';
        } else {
            throw ValidationException::withMessages([
                'device' => 'This access machine connection mode does not support remote door open.',
            ]);
        }

        return RemoteDoorOpenLog::query()->create([
            'remote_door_site_id' => $site->id,
            'zkt_device_id' => $device->id,
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'zkt_adms_command_id' => $command?->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meters' => $accuracyMeters !== null ? (int) round($accuracyMeters) : null,
            'client_ip' => $effectiveIp,
            'status' => $status,
            'result_message' => $resultMessage,
            'opened_at' => now(),
        ]);
    }

    protected function assertCooldown(Employee $employee): void
    {
        $cooldownSeconds = (int) config('zkt.door_open_cooldown_seconds', 10);

        $latest = RemoteDoorOpenLog::query()
            ->where('employee_id', $employee->id)
            ->latest('opened_at')
            ->first();

        if ($latest && $latest->opened_at?->gt(now()->subSeconds($cooldownSeconds))) {
            throw ValidationException::withMessages([
                'door' => "Please wait at least {$cooldownSeconds} seconds between door open requests.",
            ]);
        }
    }

    public function doorOpenCooldownRemaining(Employee $employee): int
    {
        $cooldownSeconds = (int) config('zkt.door_open_cooldown_seconds', 10);

        $latest = RemoteDoorOpenLog::query()
            ->where('employee_id', $employee->id)
            ->latest('opened_at')
            ->value('opened_at');

        if (! $latest) {
            return 0;
        }

        $elapsed = $latest->diffInSeconds(now());

        return max(0, $cooldownSeconds - $elapsed);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function todaysOpens(Employee $employee): array
    {
        $timezone = config('app.timezone', 'UTC');
        $start = Carbon::now($timezone)->startOfDay()->utc();
        $end = Carbon::now($timezone)->endOfDay()->utc();

        return RemoteDoorOpenLog::query()
            ->with('site:id,name', 'device:id,name')
            ->where('employee_id', $employee->id)
            ->whereBetween('opened_at', [$start, $end])
            ->orderByDesc('opened_at')
            ->get()
            ->map(fn (RemoteDoorOpenLog $log) => [
                'id' => $log->id,
                'site_name' => $log->site?->name,
                'device_name' => $log->device?->name,
                'status' => $log->status,
                'opened_at' => $log->opened_at?->timezone($timezone)->format('H:i:s'),
            ])
            ->values()
            ->all();
    }
}
