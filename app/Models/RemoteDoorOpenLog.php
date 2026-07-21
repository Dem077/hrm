<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'remote_door_site_id',
    'zkt_device_id',
    'user_id',
    'employee_id',
    'zkt_adms_command_id',
    'latitude',
    'longitude',
    'accuracy_meters',
    'client_ip',
    'status',
    'result_message',
    'opened_at',
])]
class RemoteDoorOpenLog extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'integer',
            'opened_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(RemoteDoorSite::class, 'remote_door_site_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktDevice::class, 'zkt_device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function admsCommand(): BelongsTo
    {
        return $this->belongsTo(ZktAdmsCommand::class, 'zkt_adms_command_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toMobilePunchAuditArray(): array
    {
        $this->loadMissing(
            'employee:id,staff_id,name',
            'user:id,name,email',
            'site:id,name,code',
            'device:id,name',
        );

        return [
            'id' => $this->id,
            'event_type' => 'door_open',
            'occurred_at' => $this->opened_at?->toIso8601String(),
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'staff_id' => $this->employee->staff_id,
            ] : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'site' => $this->site ? [
                'id' => $this->site->id,
                'name' => $this->site->name,
                'code' => $this->site->code,
            ] : null,
            'device' => $this->device ? [
                'id' => $this->device->id,
                'name' => $this->device->name,
            ] : null,
            'status' => $this->status,
            'result_message' => $this->result_message,
            'client_ip' => $this->client_ip,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy_meters' => $this->accuracy_meters,
            'zkt_adms_command_id' => $this->zkt_adms_command_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
