<?php

namespace App\Models;

use App\Enums\AttendanceMachineBrand;
use App\Enums\ZktConnectionMode;
use App\Enums\ZktConnectionProtocol;
use App\Enums\ZktConnectionStatus;
use App\Enums\ZktMachineType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'brand',
    'ip_address',
    'port',
    'protocol',
    'connection_mode',
    'comm_password',
    'serial_number',
    'model_name',
    'firmware_version',
    'location',
    'machine_type',
    'is_active',
    'auto_sync',
    'sync_interval_minutes',
    'connection_status',
    'last_connected_at',
    'last_synced_at',
    'last_adms_seen_at',
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
        'brand' => 'zkt',
        'protocol' => 'tcp',
        'connection_mode' => 'tcp_pull',
        'comm_password' => 0,
        'machine_type' => 'attendance',
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
            'connection_mode' => ZktConnectionMode::class,
            'brand' => AttendanceMachineBrand::class,
            'machine_type' => ZktMachineType::class,
            'last_connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_adms_seen_at' => 'datetime',
            'tcpmux_enabled' => 'boolean',
            'tcpmux_port' => 'integer',
        ];
    }

    public static function attendanceSheetDevice(): self
    {
        return static::query()->firstOrCreate(
            ['name' => 'Attendance Sheet'],
            [
                'ip_address' => '0.0.0.0',
                'machine_type' => ZktMachineType::Attendance->value,
                'is_active' => false,
                'auto_sync' => false,
                'connection_status' => 'unknown',
                'notes' => 'System device for manual punches added from the attendance sheet.',
            ],
        );
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(ZktAttendanceLog::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(ZktDeviceSyncLog::class);
    }

    public function locationGroups(): BelongsToMany
    {
        return $this->belongsToMany(ZktLocationGroup::class, 'zkt_location_group_device')
            ->withTimestamps()
            ->orderBy('zkt_location_groups.sort_order')
            ->orderBy('zkt_location_groups.name');
    }

    public function employeeSyncs(): HasMany
    {
        return $this->hasMany(ZktDeviceEmployeeSync::class);
    }

    public function admsCommands(): HasMany
    {
        return $this->hasMany(ZktAdmsCommand::class);
    }

    public function isManagedDevice(): bool
    {
        if ($this->name === 'Attendance Sheet') {
            return false;
        }

        if ($this->usesAdms()) {
            return filled($this->serial_number);
        }

        return $this->ip_address !== '0.0.0.0';
    }

    public function usesAdms(): bool
    {
        return $this->connection_mode === ZktConnectionMode::AdmsPush;
    }

    public function usesTcpPull(): bool
    {
        return $this->connection_mode === ZktConnectionMode::TcpPull;
    }

    public function isDueForSync(): bool
    {
        if (! $this->is_active || ! $this->auto_sync || $this->usesAdms()) {
            return false;
        }

        if ($this->last_synced_at === null) {
            return true;
        }

        return $this->last_synced_at->addMinutes($this->sync_interval_minutes)->isPast();
    }

    /**
     * @return array{connection_status: string, connection_status_label: string, connection_status_color: string}
     */
    public function connectionStatusPresentation(): array
    {
        if (! $this->is_active) {
            return [
                'connection_status' => 'inactive',
                'connection_status_label' => 'Inactive',
                'connection_status_color' => 'gray',
            ];
        }

        return [
            'connection_status' => $this->connection_status->value,
            'connection_status_label' => $this->connection_status->label(),
            'connection_status_color' => $this->connection_status->color(),
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $device): void {
            if ($device->isDirty('is_active') && ! $device->is_active) {
                $device->connection_status = ZktConnectionStatus::Offline;
            }
        });
    }

    protected function connectionStatus(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ZktConnectionStatus => ZktConnectionStatus::resolve($value),
            set: fn (mixed $value): string => ZktConnectionStatus::resolve($value)->value,
        );
    }
}
