<?php

namespace App\Models;

use App\Enums\BloodGroup;
use App\Enums\DutyType;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\ZktDevicePrivilege;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'staff_id',
    'name',
    'profile_photo_path',
    'current_address',
    'permanent_address',
    'ext_no',
    'personal_email',
    'office_email',
    'emergency_contact_name',
    'emergency_contact_number',
    'marital_status',
    'blood_group',
    'date_of_birth',
    'nationality',
    'religion',
    'work_location',
    'qualification',
    'employment_type',
    'bank_name',
    'account_name',
    'account_no',
    'national_id',
    'email',
    'mobile_number',
    'joined_date',
    'gender',
    'grade_id',
    'device_privilege',
    'device_card_number',
    'device_password',
    'user_id',
    'manager_id',
    'is_active',
    'works_saturday',
    'duty_type',
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
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'blood_group' => BloodGroup::class,
            'employment_type' => EmploymentType::class,
            'is_active' => 'boolean',
            'works_saturday' => 'boolean',
            'duty_type' => DutyType::class,
            'device_privilege' => ZktDevicePrivilege::class,
            'uses_custom_duty_times' => 'boolean',
            'custom_grace_minutes' => 'integer',
            'custom_saturday_grace_minutes' => 'integer',
        ];
    }

    public function isShiftDuty(): bool
    {
        return $this->duty_type->isShift();
    }

    public function usesCustomDutyTimes(): bool
    {
        if ($this->isShiftDuty()) {
            return false;
        }

        return $this->uses_custom_duty_times
            && $this->custom_duty_start_time
            && $this->custom_duty_end_time;
    }

    /**
     * @return array{start: string, end: string, grace: int}
     */
    public function resolveDutyTimes(CarbonInterface $date, AttendanceDutyPolicy $policy, ?DutyRoster $roster = null): array
    {
        if ($this->isShiftDuty()) {
            return $roster?->resolveDutyTimes() ?? [
                'start' => '00:00:00',
                'end' => '00:00:00',
                'grace' => 0,
            ];
        }

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

    public function profilePhotoUrl(): ?string
    {
        if (! $this->profile_photo_path) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->profile_photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }

    public function lengthOfServiceLabel(): ?string
    {
        if (! $this->joined_date) {
            return null;
        }

        $start = $this->joined_date->copy()->startOfDay();
        $end = now()->startOfDay();

        if ($start->greaterThan($end)) {
            return '0 days';
        }

        $diff = $start->diff($end);
        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y.' '.($diff->y === 1 ? 'year' : 'years');
        }

        if ($diff->m > 0) {
            $parts[] = $diff->m.' '.($diff->m === 1 ? 'month' : 'months');
        }

        if ($parts === [] && $diff->d > 0) {
            $parts[] = $diff->d.' '.($diff->d === 1 ? 'day' : 'days');
        }

        if ($parts === []) {
            return 'Less than 1 day';
        }

        return implode(', ', $parts);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(StructureGrade::class, 'grade_id');
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

    public function headedStructureNodes(): HasMany
    {
        return $this->hasMany(StructureNode::class, 'head_employee_id');
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

    public function dutyRosters(): HasMany
    {
        return $this->hasMany(DutyRoster::class);
    }

    public function zktLocationGroups(): BelongsToMany
    {
        return $this->belongsToMany(ZktLocationGroup::class, 'employee_zkt_location_group')
            ->withTimestamps()
            ->orderBy('zkt_location_groups.sort_order')
            ->orderBy('zkt_location_groups.name');
    }

    public function selfPunchSites(): BelongsToMany
    {
        return $this->belongsToMany(SelfPunchSite::class, 'employee_self_punch_site')
            ->withTimestamps()
            ->orderBy('self_punch_sites.sort_order')
            ->orderBy('self_punch_sites.name');
    }

    public function remoteDoorSites(): BelongsToMany
    {
        return $this->belongsToMany(RemoteDoorSite::class, 'employee_remote_door_site')
            ->withTimestamps()
            ->orderBy('remote_door_sites.sort_order')
            ->orderBy('remote_door_sites.name');
    }

    public function zktDeviceSyncs(): HasMany
    {
        return $this->hasMany(ZktDeviceEmployeeSync::class);
    }

    public function payrollRunItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function payrollRunAdjustments(): HasMany
    {
        return $this->hasMany(PayrollRunAdjustment::class);
    }
}
