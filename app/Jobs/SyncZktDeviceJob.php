<?php

namespace App\Jobs;

use App\Models\ZktDevice;
use App\Services\Zkt\ZktDeviceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncZktDeviceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ZktDevice $device,
    ) {}

    public function handle(ZktDeviceSyncService $syncService): void
    {
        $syncService->sync($this->device);
    }
}
