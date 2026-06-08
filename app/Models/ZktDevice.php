<?php

namespace App\Models;

use App\Enums\ZktConnectionProtocol;
use App\Enums\ZktConnectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'ip_address',
    'port',
    'protocol',
    'comm_password',
    'serial_number',
    'model_name',
    'firmware_version',
    'location',
    'is_active',
    'auto_sync',
    'sync_interval_minutes',
    'connection_status',
    'last_connected_at',
    'last_synced_at',
    'last_sync_error',
    'notes',
    'tcpmux_enabled',
    'tcpmux_subdomain',
    'tcpmux_port',
])]
class ZktDevice extends Model
{
    protected $attributes = [
        'port' => 4370,
        'protocol' => 'tcp',
        'comm_password' => 0,
        'is_active' => true,
        'auto_sync' => true,
        'sync_interval_minutes' => 10,
        'connection_status' => 'unknown',
        'tcpmux_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'comm_password' => 'integer',
            'is_active' => 'boolean',
            'auto_sync' => 'boolean',
            'sync_interval_minutes' => 'integer',
            'protocol' => ZktConnectionProtocol::class,
            'last_connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'tcpmux_enabled' => 'boolean',
            'tcpmux_port' => 'integer',
        ];
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(ZktAttendanceLog::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(ZktDeviceSyncLog::class);
    }

    public function isDueForSync(): bool
    {
        if (! $this->is_active || ! $this->auto_sync) {
            return false;
        }

        if ($this->last_synced_at === null) {
            return true;
        }

        return $this->last_synced_at->addMinutes($this->sync_interval_minutes)->isPast();
    }

    protected function connectionStatus(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ZktConnectionStatus => ZktConnectionStatus::resolve($value),
            set: fn (mixed $value): string => ZktConnectionStatus::resolve($value)->value,
        );
    }
}
