<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'zkt_device_id',
    'device_uid',
    'device_user_id',
    'punch_state',
    'punch_type',
    'punched_at',
])]
class ZktAttendanceLog extends Model
{
    protected function casts(): array
    {
        return [
            'device_uid' => 'integer',
            'punch_state' => 'integer',
            'punch_type' => 'integer',
            'punched_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktDevice::class, 'zkt_device_id');
    }

    public function punchStateLabel(): string
    {
        return match ($this->punch_state) {
            0 => 'Check In',
            1 => 'Check Out',
            2 => 'Break Out',
            3 => 'Break In',
            4 => 'Overtime In',
            5 => 'Overtime Out',
            default => 'Unknown',
        };
    }
}
