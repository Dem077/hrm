<?php

namespace App\Console\Commands;

use App\Enums\ZktAdmsCommandStatus;
use App\Enums\ZktMachineType;
use App\Models\ZktAdmsCommand;
use App\Models\ZktDevice;
use App\Services\Adms\AdmsCommandQueue;
use App\Services\Adms\AdmsUserCommandBuilder;
use App\Services\Zkt\ZktDeviceClient;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class SyncZktDeviceTimesCommand extends Command
{
    protected $signature = 'zkt:sync-time
                            {--device= : Sync time on a specific device by ID}';

    protected $description = 'Sync attendance machine clocks with the server (TCP pull + ADMS SyncTime option)';

    public function handle(
        ZktDeviceClient $client,
        AdmsCommandQueue $admsCommandQueue,
        AdmsUserCommandBuilder $admsUserCommandBuilder,
    ): int {
        $devices = $this->resolveDevices();

        if ($devices->isEmpty()) {
            $this->warn('No attendance machines matched the time sync criteria.');

            return self::SUCCESS;
        }

        $interval = max(1, (int) config('zkt.time_sync_interval_seconds', 60));
        $this->info("Syncing clocks on {$devices->count()} attendance machine(s) (interval {$interval}s)...");

        $ok = 0;
        $failed = 0;

        foreach ($devices as $device) {
            try {
                if ($device->usesAdms()) {
                    $this->ensureAdmsSyncTimeOption($device, $admsCommandQueue, $admsUserCommandBuilder);
                    $this->line("{$device->name}: ADMS SyncTime={$interval}s ensured");
                    $ok++;

                    continue;
                }

                $result = $client->syncDeviceTime($device);
                $this->info("{$device->name}: ".$result['device_time_after']);
                $ok++;
            } catch (Throwable $exception) {
                $this->error("{$device->name}: {$exception->getMessage()}");
                $failed++;
            }
        }

        $this->info("Time sync finished. OK={$ok}, failed={$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, ZktDevice>
     */
    protected function resolveDevices(): Collection
    {
        $query = ZktDevice::query()
            ->where('is_active', true)
            ->where('machine_type', ZktMachineType::Attendance->value);

        if ($deviceId = $this->option('device')) {
            $query->whereKey($deviceId);
        }

        return $query->orderBy('name')->get()->filter(
            fn (ZktDevice $device) => $device->isManagedDevice()
        )->values();
    }

    protected function ensureAdmsSyncTimeOption(
        ZktDevice $device,
        AdmsCommandQueue $admsCommandQueue,
        AdmsUserCommandBuilder $admsUserCommandBuilder,
    ): void {
        $payload = $admsUserCommandBuilder->buildSyncTimeOptionCommand();

        $alreadyQueued = ZktAdmsCommand::query()
            ->where('zkt_device_id', $device->id)
            ->where('payload', $payload)
            ->whereIn('status', [
                ZktAdmsCommandStatus::Pending->value,
                ZktAdmsCommandStatus::Sent->value,
            ])
            ->exists();

        if ($alreadyQueued) {
            return;
        }

        $recentlyApplied = ZktAdmsCommand::query()
            ->where('zkt_device_id', $device->id)
            ->where('payload', $payload)
            ->where('status', ZktAdmsCommandStatus::Done->value)
            ->where('completed_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($recentlyApplied) {
            return;
        }

        $admsCommandQueue->enqueue($device, $payload);
    }
}
