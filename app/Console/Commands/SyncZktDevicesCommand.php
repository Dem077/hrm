<?php

namespace App\Console\Commands;

use App\Jobs\SyncZktDeviceJob;
use App\Models\ZktDevice;
use App\Services\Zkt\ZktDeviceSyncService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class SyncZktDevicesCommand extends Command
{
    protected $signature = 'zkt:sync
                            {--device= : Sync a specific device by ID}
                            {--force : Sync all active devices regardless of schedule}
                            {--queue : Dispatch sync jobs to the queue instead of running inline}';

    protected $description = 'Sync attendance logs from ZKT biometric devices';

    public function handle(ZktDeviceSyncService $syncService): int
    {
        $devices = $this->resolveDevices();

        if ($devices->isEmpty()) {
            $this->warn('No ZKT devices matched the sync criteria.');

            return self::SUCCESS;
        }

        $this->info("Syncing {$devices->count()} device(s)...");

        foreach ($devices as $device) {
            if ($this->option('queue')) {
                SyncZktDeviceJob::dispatch($device);
                $this->line("Queued sync for: {$device->name}");

                continue;
            }

            $syncLog = $syncService->sync($device);

            if ($syncLog->status->value === 'success') {
                $this->info("{$device->name}: {$syncLog->message}");
            } else {
                $this->error("{$device->name}: {$syncLog->message}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, ZktDevice>
     */
    protected function resolveDevices()
    {
        $query = ZktDevice::query()->where('is_active', true);

        if ($deviceId = $this->option('device')) {
            return $query->whereKey($deviceId)->get();
        }

        if (! $this->option('force')) {
            $query->where('auto_sync', true);
        }

        return $query->get()->filter(
            fn (ZktDevice $device) => $this->option('force') || $device->isDueForSync()
        )->values();
    }
}
