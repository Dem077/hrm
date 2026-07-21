<?php

namespace App\Models;

use App\Support\Concerns\HasGeofenceSiteRules;
use App\Support\PublicIp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'zkt_device_id',
    'name',
    'code',
    'description',
    'latitude',
    'longitude',
    'radius_meters',
    'max_accuracy_meters',
    'allowed_public_ips',
    'require_public_ip',
    'sort_order',
    'is_active',
])]
class RemoteDoorSite extends Model
{
    use HasGeofenceSiteRules;

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'max_accuracy_meters' => 'integer',
            'allowed_public_ips' => 'array',
            'require_public_ip' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktDevice::class, 'zkt_device_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_remote_door_site')
            ->withTimestamps()
            ->orderBy('employees.name');
    }

    public function openLogs(): HasMany
    {
        return $this->hasMany(RemoteDoorOpenLog::class);
    }

    /**
     * @return Collection<int, RemoteDoorSite>
     */
    public static function activeSitesForEmployee(Employee $employee): Collection
    {
        return static::query()
            ->with('device:id,name,connection_mode,serial_number,is_active')
            ->where('is_active', true)
            ->whereHas('employees', fn ($query) => $query->where('employees.id', $employee->id))
            ->whereHas('device', fn ($query) => $query
                ->where('is_active', true)
                ->where('machine_type', 'access'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        return [
            'id' => $this->id,
            'zkt_device_id' => $this->zkt_device_id,
            'device_name' => $this->device?->name,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_meters' => $this->radius_meters,
            'max_accuracy_meters' => $this->max_accuracy_meters,
            'allowed_public_ips' => $this->allowedPublicIpList(),
            'allowed_public_ips_text' => implode("\n", $this->allowedPublicIpList()),
            'require_public_ip' => $this->require_public_ip,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'employees_count' => $this->employees_count ?? $this->employees()->count(),
            'employee_ids' => $this->relationLoaded('employees')
                ? $this->employees->pluck('id')->values()->all()
                : [],
            'employees' => $this->relationLoaded('employees')
                ? $this->employees->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'staff_id' => $employee->staff_id,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toEmployeeFacingArray(?string $clientIp = null): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_meters' => $this->radius_meters,
            'max_accuracy_meters' => $this->max_accuracy_meters,
            'require_public_ip' => $this->require_public_ip,
            'allowed_public_ips' => $this->require_public_ip ? $this->allowedPublicIpList() : [],
            'public_ip_allowed' => $this->require_public_ip
                ? (PublicIp::isPublic($clientIp) ? $this->clientIpIsAllowed($clientIp) : null)
                : true,
            'device' => [
                'id' => $this->device?->id,
                'name' => $this->device?->name,
                'connection_mode' => $this->device?->connection_mode?->value ?? $this->device?->connection_mode,
            ],
        ];
    }
}
