<?php

namespace App\Models;

use App\Enums\ZktSyncStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'zkt_device_id',
    'status',
    'records_fetched',
    'records_stored',
    'message',
    'started_at',
    'completed_at',
])]
class ZktDeviceSyncLog extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ZktSyncStatus::class,
            'records_fetched' => 'integer',
            'records_stored' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktDevice::class, 'zkt_device_id');
    }
}
