<?php

namespace App\Models;

use App\Enums\Gender;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'staff_id',
    'name',
    'national_id',
    'email',
    'mobile_number',
    'joined_date',
    'gender',
    'department_id',
    'user_id',
    'manager_id',
    'is_active',
    'works_saturday',
    'uses_custom_duty_times',
    'custom_duty_start_time',
    'custom_duty_end_time',
    'custom_grace_minutes',
    'custom_saturday_duty_start_time',
    'custom_saturday_duty_end_time',
    'custom_saturday_grace_minutes',
])]
class Employee extends Model
{
    protected function casts(): array
    {
        return [
            'joined_date' => 'date',
            'gender' => Gender::class,
            'is_active' => 'boolean',
            'works_saturday' => 'boolean',
            'uses_custom_duty_times' => 'boolean',
            'custom_grace_minutes' => 'integer',
            'custom_saturday_grace_minutes' => 'integer',
        ];
    }

    public function usesCustomDutyTimes(): bool
    {
        return $this->uses_custom_duty_times
            && $this->custom_duty_start_time
            && $this->custom_duty_end_time;
    }

    /**
     * @return array{start: string, end: string, grace: int}
     */
    public function resolveDutyTimes(CarbonInterface $date, AttendanceDutyPolicy $policy): array
    {
        if (! $this->usesCustomDutyTimes()) {
            return $policy->resolveDutyTimes($date, $this);
        }

        if ($date->dayOfWeek === Carbon::SATURDAY && $this->works_saturday) {
            return [
                'start' => (string) ($this->custom_saturday_duty_start_time ?? $this->custom_duty_start_time),
                'end' => (string) ($this->custom_saturday_duty_end_time ?? $this->custom_duty_end_time),
                'grace' => (int) ($this->custom_saturday_grace_minutes ?? $this->custom_grace_minutes ?? 15),
            ];
        }

        return [
            'start' => (string) $this->custom_duty_start_time,
            'end' => (string) $this->custom_duty_end_time,
            'grace' => (int) ($this->custom_grace_minutes ?? 15),
        ];
    }

    public function formatCustomTimeForInput(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return substr($time, 0, 5);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_employee_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(ZktAttendanceLog::class, 'device_user_id', 'staff_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveApprovals(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approver_employee_id');
    }
}
