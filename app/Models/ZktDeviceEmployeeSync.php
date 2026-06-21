<?php

namespace App\Models;

use App\Enums\ZktDeviceUserSyncStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'zkt_device_id',
    'device_uid',
    'sync_status',
    'last_synced_at',
    'last_error',
])]
class ZktDeviceEmployeeSync extends Model
{
    protected function casts(): array
    {
        return [
            'device_uid' => 'integer',
            'sync_status' => ZktDeviceUserSyncStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktDevice::class, 'zkt_device_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        $status = $this->sync_status ?? ZktDeviceUserSyncStatus::Pending;

        return [
            'id' => $this->id,
            'device_uid' => $this->device_uid,
            'sync_status' => $status->value,
            'sync_status_label' => $status->label(),
            'sync_status_color' => $status->color(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'device' => $this->relationLoaded('device') && $this->device ? [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'location' => $this->device->location,
            ] : null,
        ];
    }
}
