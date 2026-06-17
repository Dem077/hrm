<?php

namespace App\Services\Attendance;

use App\Enums\AttendancePunchSource;
use App\Models\Employee;
use App\Models\User;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ManualAttendancePunchService
{
    public function create(
        Employee $employee,
        Carbon $punchedAt,
        int $punchState,
        string $reason,
        User $addedBy,
    ): ZktAttendanceLog {
        if (! in_array($punchState, [0, 1], true)) {
            throw ValidationException::withMessages([
                'punch_state' => 'Only check in or check out punches can be added manually.',
            ]);
        }

        $device = ZktDevice::attendanceSheetDevice();
        $deviceUid = (int) (ZktAttendanceLog::query()
            ->where('zkt_device_id', $device->id)
            ->max('device_uid') ?? 0) + 1;

        return ZktAttendanceLog::query()->create([
            'zkt_device_id' => $device->id,
            'device_uid' => $deviceUid,
            'device_user_id' => $employee->staff_id,
            'punch_state' => $punchState,
            'punch_type' => null,
            'punched_at' => $punchedAt,
            'source' => AttendancePunchSource::AttendanceSheet,
            'manual_reason' => $reason,
            'added_by_user_id' => $addedBy->id,
        ]);
    }

    /**
     * @param  Collection<int, ZktAttendanceLog>  $logs
     */
    public function removeMany(Collection $logs, string $reason, User $removedBy): void
    {
        foreach ($logs as $log) {
            $log->forceFill([
                'removal_reason' => $reason,
                'removed_by_user_id' => $removedBy->id,
            ])->save();

            $log->delete();
        }
    }

    /**
     * @param  list<int>  $logIds
     * @return Collection<int, ZktAttendanceLog>
     */
    public function resolveRemovableLogs(array $logIds, User $user, bool $canViewAll): Collection
    {
        $logs = ZktAttendanceLog::query()
            ->with('employee:id,staff_id')
            ->whereIn('id', $logIds)
            ->get();

        if ($logs->count() !== count(array_unique($logIds))) {
            throw ValidationException::withMessages([
                'punch_log_ids' => 'One or more punch records could not be found.',
            ]);
        }

        if (! $canViewAll) {
            $ownStaffId = $user->employee?->staff_id;

            if (! $ownStaffId) {
                throw ValidationException::withMessages([
                    'punch_log_ids' => 'Your account is not linked to an employee record.',
                ]);
            }

            $invalid = $logs->first(fn (ZktAttendanceLog $log) => $log->device_user_id !== $ownStaffId);

            if ($invalid) {
                throw ValidationException::withMessages([
                    'punch_log_ids' => 'You can only remove punches for your own attendance.',
                ]);
            }
        }

        $invalidState = $logs->first(fn (ZktAttendanceLog $log) => ! in_array($log->punch_state, [0, 1], true));

        if ($invalidState) {
            throw ValidationException::withMessages([
                'punch_log_ids' => 'Only check in or check out punches can be removed.',
            ]);
        }

        return $logs;
    }
}
