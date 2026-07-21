<?php

namespace App\Services\Adms;

use App\Enums\ZktConnectionMode;
use App\Enums\ZktConnectionStatus;
use App\Models\ZktDevice;
use Illuminate\Support\Facades\Log;

class AdmsDeviceResolver
{
    public function resolve(?string $serialNumber): ?ZktDevice
    {
        $serialNumber = trim((string) $serialNumber);

        if ($serialNumber === '') {
            return null;
        }

        $device = ZktDevice::query()
            ->where('serial_number', $serialNumber)
            ->where('is_active', true)
            ->where('connection_mode', ZktConnectionMode::AdmsPush->value)
            ->first();

        if (! $device) {
            Log::info('ADMS request for unknown or inactive serial number.', [
                'serial_number' => $serialNumber,
            ]);
        }

        return $device;
    }

    public function touch(ZktDevice $device, ?string $firmware = null, ?string $model = null): void
    {
        $updates = [
            'last_adms_seen_at' => now(),
            'last_connected_at' => now(),
            'connection_status' => ZktConnectionStatus::Online,
            'last_sync_error' => null,
        ];

        if (filled($firmware)) {
            $updates['firmware_version'] = $firmware;
        }

        if (filled($model)) {
            $updates['model_name'] = $model;
        }

        $device->update($updates);
    }
}
