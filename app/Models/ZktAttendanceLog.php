<?php

namespace App\Models;

use App\Enums\AttendancePunchSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'zkt_device_id',
    'device_uid',
    'device_user_id',
    'punch_state',
    'punch_type',
    'punched_at',
    'source',
    'self_punch_site_id',
    'latitude',
    'longitude',
    'accuracy_meters',
    'client_ip',
    'request_ip',
    'client_device_id',
    'manual_reason',
    'added_by_user_id',
    'removal_reason',
    'removed_by_user_id',
])]
class ZktAttendanceLog extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'device_uid' => 'integer',
            'punch_state' => 'integer',
            'punch_type' => 'integer',
            'punched_at' => 'datetime',
            'source' => AttendancePunchSource::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ZktDevice::class, 'zkt_device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'device_user_id', 'staff_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by_user_id');
    }

    public function selfPunchSite(): BelongsTo
    {
        return $this->belongsTo(SelfPunchSite::class);
    }

    public function isFromAttendanceSheet(): bool
    {
        return $this->source === AttendancePunchSource::AttendanceSheet;
    }

    public function isSelfPunch(): bool
    {
        return $this->source === AttendancePunchSource::SelfApp;
    }

    /**
     * @param  Builder<ZktAttendanceLog>  $query
     */
    public function scopeForPunchLog(Builder $query): Builder
    {
        return $query
            ->withTrashed()
            ->where(function (Builder $inner) {
                $inner->whereNull('source')
                    ->orWhere('source', '!=', AttendancePunchSource::AttendanceSheet->value);
            });
    }

    public function sourceValue(): string
    {
        $source = $this->source;

        return $source instanceof AttendancePunchSource ? $source->value : (string) ($source ?? AttendancePunchSource::Device->value);
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

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        $this->loadMissing('employee:id,staff_id,name');

        return [
            'id' => $this->id,
            'device_user_id' => $this->device_user_id,
            'emp_no' => $this->employee?->staff_id ?? $this->device_user_id,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'staff_id' => $this->employee->staff_id,
            ] : null,
            'device_uid' => $this->device_uid,
            'punch_state_label' => $this->punchStateLabel(),
            'punched_at' => $this->punched_at?->toIso8601String(),
            'source' => $this->sourceValue(),
            'source_label' => ($this->source instanceof AttendancePunchSource ? $this->source : AttendancePunchSource::tryFrom($this->sourceValue()))?->label() ?? 'Device',
            'self_punch_site_id' => $this->self_punch_site_id,
            'manual_reason' => $this->manual_reason,
            'is_manual' => $this->isFromAttendanceSheet(),
            'is_self_punch' => $this->isSelfPunch(),
            'is_removed' => $this->trashed(),
            'removal_reason' => $this->removal_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toMobilePunchAuditArray(): array
    {
        $this->loadMissing('employee:id,staff_id,name', 'selfPunchSite:id,name,code');

        return [
            'id' => $this->id,
            'event_type' => 'punch',
            'occurred_at' => $this->punched_at?->toIso8601String(),
            'punch_state' => $this->punch_state,
            'punch_state_label' => $this->punchStateLabel(),
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'staff_id' => $this->employee->staff_id,
            ] : null,
            'device_user_id' => $this->device_user_id,
            'site' => $this->selfPunchSite ? [
                'id' => $this->selfPunchSite->id,
                'name' => $this->selfPunchSite->name,
                'code' => $this->selfPunchSite->code,
            ] : null,
            'client_ip' => $this->client_ip,
            'request_ip' => $this->request_ip,
            'client_device_id' => $this->client_device_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy_meters' => $this->accuracy_meters,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
