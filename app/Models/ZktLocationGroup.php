<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[Fillable([
    'name',
    'code',
    'description',
    'sort_order',
    'is_active',
])]
class ZktLocationGroup extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(ZktDevice::class, 'zkt_location_group_device')
            ->withTimestamps()
            ->orderBy('zkt_devices.name');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_zkt_location_group')
            ->withTimestamps()
            ->orderBy('employees.name');
    }

    /**
     * @return list<int>
     */
    public function eligibleEmployeeIds(): array
    {
        return $this->employees()
            ->where('employees.is_active', true)
            ->pluck('employees.id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, ZktLocationGroup>
     */
    public static function activeGroupsForEmployee(Employee $employee): Collection
    {
        return static::query()
            ->where('is_active', true)
            ->whereHas('employees', fn ($employeeQuery) => $employeeQuery->where('employees.id', $employee->id))
            ->with(['devices' => fn ($query) => $query->where('is_active', true)])
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
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'devices_count' => $this->devices_count ?? $this->devices()->count(),
            'employees_count' => $this->employees_count ?? $this->employees()->count(),
            'device_ids' => $this->relationLoaded('devices')
                ? $this->devices->pluck('id')->values()->all()
                : [],
            'devices' => $this->relationLoaded('devices')
                ? $this->devices->map(fn (ZktDevice $device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'location' => $device->location,
                    'is_active' => $device->is_active,
                ])->values()->all()
                : [],
        ];
    }
}
