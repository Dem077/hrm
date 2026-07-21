<?php

namespace App\Services\Attendance;

use App\Enums\DutyType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DutyRosterScopeService
{
    public function __construct(private readonly ?User $user) {}

    public function canViewAll(): bool
    {
        return $this->user?->can('duty-rosters.view-all') ?? false;
    }

    public function scopedDepartmentId(): ?int
    {
        if ($this->canViewAll()) {
            return null;
        }

        $employee = $this->user?->employee;

        if (! $employee) {
            return null;
        }

        $employee->loadMissing('grade.level');

        return $employee->grade?->level?->structure_node_id;
    }

    public function resolveFilterDepartment(?int $requestedDepartmentId): ?int
    {
        $scopedDepartmentId = $this->scopedDepartmentId();

        if ($scopedDepartmentId !== null) {
            return $scopedDepartmentId;
        }

        return $requestedDepartmentId;
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function applyShiftEmployeeScope(Builder $query): Builder
    {
        $query->where('duty_type', DutyType::Shift)
            ->where('is_active', true);

        $departmentId = $this->scopedDepartmentId();

        if ($departmentId === null) {
            if ($this->canViewAll()) {
                return $query;
            }

            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'grade.level',
            fn ($levelQuery) => $levelQuery->where('structure_node_id', $departmentId),
        );
    }

    public function shiftEmployeeQuery(): Builder
    {
        return $this->applyShiftEmployeeScope(Employee::query());
    }

    public function employeeIsAccessible(int $employeeId): bool
    {
        return $this->shiftEmployeeQuery()->whereKey($employeeId)->exists();
    }

    /**
     * @param  list<int>  $employeeIds
     * @return list<int>
     */
    public function inaccessibleEmployeeIds(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $accessibleIds = $this->shiftEmployeeQuery()
            ->whereIn('id', $employeeIds)
            ->pluck('id')
            ->all();

        return array_values(array_diff($employeeIds, $accessibleIds));
    }
}
