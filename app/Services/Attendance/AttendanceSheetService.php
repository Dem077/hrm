<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\AttendanceDutyPolicy;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\ZktAttendanceLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceSheetService
{
    public const MAX_DAYS = 31;

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function build(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $departmentId = null,
        ?int $employeeId = null,
    ): array {
        $timezone = config('app.timezone', 'UTC');

        $from = $from->copy()->timezone($timezone)->startOfDay();
        $to = $to->copy()->timezone($timezone)->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $maxDays = self::MAX_DAYS;

        if ($from->diffInDays($to) > $maxDays) {
            $to = $from->copy()->addDays($maxDays);
        }

        $policies = AttendanceDutyPolicy::allOrdered();

        $holidays = PublicHoliday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (PublicHoliday $holiday) => $holiday->date->toDateString());

        $employees = Employee::query()
            ->with('department:id,name')
            ->where('is_active', true)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($employeeId, fn ($query) => $query->where('id', $employeeId))
            ->orderBy('name')
            ->get([
                'id',
                'staff_id',
                'name',
                'department_id',
                'works_saturday',
                'uses_custom_duty_times',
                'custom_duty_start_time',
                'custom_duty_end_time',
                'custom_grace_minutes',
                'custom_saturday_duty_start_time',
                'custom_saturday_duty_end_time',
                'custom_saturday_grace_minutes',
            ]);

        $staffIds = $employees->pluck('staff_id')->filter()->values();

        $punchIndex = $this->indexPunches($staffIds, $from, $to, $timezone);
        $leaveIndex = $this->indexApprovedLeave($employees, $from, $to, $timezone);

        $rows = [];

        for ($date = $from->copy(); $date->lte($to); $date = $date->addDay()) {
            $dateKey = $date->toDateString();
            $holiday = $holidays->get($dateKey);
            $policy = AttendanceDutyPolicy::forDate($date, $policies);

            foreach ($employees as $employee) {
                $dayPunches = collect($punchIndex[$employee->staff_id][$dateKey] ?? []);
                $weekendHoliday = $this->resolveWeekendHoliday($employee, $date);
                $approvedLeaveType = $leaveIndex[$employee->id][$dateKey] ?? null;

                $rows[] = $this->buildRow(
                    $employee,
                    $date,
                    $dayPunches,
                    $policy,
                    $holiday,
                    $weekendHoliday,
                    $timezone,
                    $approvedLeaveType,
                );
            }
        }

        return [
            'rows' => $rows,
            'total' => count($rows),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{
     *     present_days: int,
     *     absent_days: int,
     *     leave_days: int,
     *     late_minutes: int
     * }
     */
    public function summarizeRows(array $rows): array
    {
        $presentDays = 0;
        $absentDays = 0;
        $leaveDays = 0;
        $lateMinutes = 0;

        foreach ($rows as $row) {
            $status = AttendanceDayStatus::from($row['status']);

            if (in_array($status, [
                AttendanceDayStatus::Present,
                AttendanceDayStatus::Late,
                AttendanceDayStatus::Incomplete,
            ], true)) {
                $presentDays++;
            } elseif ($status === AttendanceDayStatus::Absent) {
                $absentDays++;
            } elseif ($status === AttendanceDayStatus::Leave) {
                $leaveDays++;
            }

            $lateMinutes += (int) ($row['late_minutes'] ?? 0);
        }

        return [
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'late_minutes' => $lateMinutes,
        ];
    }

    protected function resolveWeekendHoliday(Employee $employee, CarbonInterface $date): ?string
    {
        if ($date->dayOfWeek === Carbon::FRIDAY) {
            return 'Friday';
        }

        if ($date->dayOfWeek === Carbon::SATURDAY && ! $employee->works_saturday) {
            return 'Saturday';
        }

        return null;
    }

    /**
     * @param  Collection<int, non-empty-string>  $staffIds
     * @return array<string, array<string, list<ZktAttendanceLog>>>
     */
    protected function indexPunches(
        Collection $staffIds,
        CarbonInterface $from,
        CarbonInterface $to,
        string $timezone,
    ): array {
        if ($staffIds->isEmpty()) {
            return [];
        }

        $index = [];

        ZktAttendanceLog::query()
            ->select(['id', 'device_user_id', 'punch_state', 'punched_at'])
            ->whereIn('device_user_id', $staffIds)
            ->whereBetween('punched_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ])
            ->orderBy('punched_at')
            ->chunkById(1000, function ($logs) use (&$index, $timezone) {
                foreach ($logs as $log) {
                    $dateKey = $log->punched_at->timezone($timezone)->toDateString();
                    $index[$log->device_user_id][$dateKey][] = $log;
                }
            });

        return $index;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<string, non-empty-string>>
     */
    protected function indexApprovedLeave(
        Collection $employees,
        CarbonInterface $from,
        CarbonInterface $to,
        string $timezone,
    ): array {
        $employeeIds = $employees->pluck('id');

        if ($employeeIds->isEmpty()) {
            return [];
        }

        $index = [];

        LeaveRequest::query()
            ->with('leaveType:id,name')
            ->where('status', LeaveRequestStatus::Approved)
            ->whereIn('employee_id', $employeeIds)
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get()
            ->each(function (LeaveRequest $leave) use (&$index, $from, $to, $timezone): void {
                $start = $leave->start_date->copy()->timezone($timezone)->startOfDay();
                $end = $leave->end_date->copy()->timezone($timezone)->startOfDay();

                if ($start->lt($from)) {
                    $start = $from->copy();
                }

                if ($end->gt($to)) {
                    $end = $to->copy();
                }

                $leaveTypeName = $leave->leaveType?->name ?? 'Leave';

                for ($date = $start->copy(); $date->lte($end); $date = $date->addDay()) {
                    $index[$leave->employee_id][$date->toDateString()] = $leaveTypeName;
                }
            });

        return $index;
    }

    /**
     * @param  Collection<int, ZktAttendanceLog>  $dayPunches
     * @return array<string, mixed>
     */
    protected function buildRow(
        Employee $employee,
        CarbonInterface $date,
        Collection $dayPunches,
        AttendanceDutyPolicy $policy,
        ?PublicHoliday $holiday,
        ?string $weekendHoliday,
        string $timezone,
        ?string $approvedLeaveType = null,
    ): array {
        $dutyTimes = $employee->resolveDutyTimes($date, $policy);

        if ($holiday) {
            return $this->baseRow($employee, $date, $dutyTimes, [
                'check_in' => null,
                'check_out' => null,
                'working_minutes' => null,
                'working_hours_label' => '—',
                'late_minutes' => null,
                'status' => AttendanceDayStatus::Holiday,
                'holiday_name' => $holiday->name,
            ], isHoliday: true);
        }

        if ($weekendHoliday) {
            return $this->baseRow($employee, $date, $dutyTimes, [
                'check_in' => null,
                'check_out' => null,
                'working_minutes' => null,
                'working_hours_label' => '—',
                'late_minutes' => null,
                'status' => AttendanceDayStatus::Holiday,
                'holiday_name' => $weekendHoliday,
            ], isHoliday: true);
        }

        [$checkIn, $checkOut] = $this->resolvePunchPair($dayPunches, $date, $dutyTimes, $timezone);

        if (! $checkIn && ! $checkOut) {
            if ($approvedLeaveType) {
                return $this->baseRow($employee, $date, $dutyTimes, [
                    'check_in' => null,
                    'check_out' => null,
                    'working_minutes' => null,
                    'working_hours_label' => '—',
                    'late_minutes' => null,
                    'status' => AttendanceDayStatus::Leave,
                    'status_label' => $approvedLeaveType,
                    'holiday_name' => null,
                    'leave_type_name' => $approvedLeaveType,
                ]);
            }

            return $this->baseRow($employee, $date, $dutyTimes, [
                'check_in' => null,
                'check_out' => null,
                'working_minutes' => null,
                'working_hours_label' => '—',
                'late_minutes' => null,
                'status' => AttendanceDayStatus::Absent,
                'holiday_name' => null,
            ]);
        }

        $lateMinutes = $checkIn
            ? $this->calculateLateMinutes($checkIn, $date, $dutyTimes, $timezone)
            : 0;
        $workingMinutes = null;
        $status = AttendanceDayStatus::Present;

        if (! $checkIn || ! $checkOut) {
            $status = AttendanceDayStatus::Incomplete;
        } else {
            $workingMinutes = $checkIn->diffInMinutes($checkOut, false);

            if ($workingMinutes < 0) {
                $workingMinutes = null;
                $status = AttendanceDayStatus::Incomplete;
            }
        }

        if ($lateMinutes > 0 && $status === AttendanceDayStatus::Present) {
            $status = AttendanceDayStatus::Late;
        }

        return $this->baseRow($employee, $date, $dutyTimes, [
            'check_in' => $checkIn?->toIso8601String(),
            'check_out' => $checkOut?->toIso8601String(),
            'working_minutes' => $workingMinutes,
            'working_hours_label' => $workingMinutes !== null ? $this->formatMinutes($workingMinutes) : '—',
            'late_minutes' => $lateMinutes > 0 ? $lateMinutes : null,
            'status' => $status,
            'holiday_name' => null,
        ]);
    }

    /**
     * @param  array{start: string, end: string, grace: int}  $dutyTimes
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function baseRow(
        Employee $employee,
        CarbonInterface $date,
        array $dutyTimes,
        array $values,
        bool $isHoliday = false,
    ): array {
        /** @var AttendanceDayStatus $status */
        $status = $values['status'];

        return [
            'date' => $date->toDateString(),
            'employee_id' => $employee->id,
            'staff_id' => $employee->staff_id,
            'employee_name' => $employee->name,
            'department' => $employee->department?->name,
            'check_in' => $values['check_in'],
            'check_out' => $values['check_out'],
            'working_minutes' => $values['working_minutes'],
            'working_hours_label' => $values['working_hours_label'],
            'late_minutes' => $values['late_minutes'],
            'status' => $status->value,
            'status_label' => $values['status_label'] ?? $status->label(),
            'status_color' => $values['status_color'] ?? $status->color(),
            'holiday_name' => $values['holiday_name'],
            'leave_type_name' => $values['leave_type_name'] ?? null,
            'duty_start_time' => $isHoliday ? '—' : substr($dutyTimes['start'], 0, 5),
            'duty_end_time' => $isHoliday ? '—' : substr($dutyTimes['end'], 0, 5),
        ];
    }

    /**
     * @param  Collection<int, ZktAttendanceLog>  $dayPunches
     * @param  array{start: string, end: string, grace: int}  $dutyTimes
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface}
     */
    protected function resolvePunchPair(
        Collection $dayPunches,
        CarbonInterface $date,
        array $dutyTimes,
        string $timezone,
    ): array {
        if ($dayPunches->isEmpty()) {
            return [null, null];
        }

        $dutyStart = Carbon::parse($date->toDateString().' '.$dutyTimes['start'], $timezone);
        $dutyEnd = Carbon::parse($date->toDateString().' '.$dutyTimes['end'], $timezone);

        $punches = $dayPunches
            ->map(function (ZktAttendanceLog $log) use ($timezone, $dutyStart, $dutyEnd) {
                $at = $log->punched_at->copy()->timezone($timezone);
                $toStart = abs($at->diffInMinutes($dutyStart, false));
                $toEnd = abs($at->diffInMinutes($dutyEnd, false));

                return [
                    'at' => $at,
                    'to_start' => $toStart,
                    'to_end' => $toEnd,
                ];
            })
            ->sortBy(fn (array $punch) => $punch['at']->timestamp)
            ->values();

        $inCandidates = $punches->filter(fn (array $punch) => $punch['to_start'] <= $punch['to_end']);
        $outCandidates = $punches->filter(fn (array $punch) => $punch['to_end'] < $punch['to_start']);

        $checkIn = $inCandidates->sortBy('to_start')->first()['at'] ?? null;

        $checkOut = $outCandidates->sortBy('to_end')->first()['at'] ?? null;

        if ($checkIn && $checkOut && $checkOut->lte($checkIn)) {
            $checkOut = $outCandidates
                ->filter(fn (array $punch) => $punch['at']->gt($checkIn))
                ->sortBy('to_end')
                ->first()['at'] ?? null;
        }

        return [$checkIn, $checkOut];
    }

    /**
     * @param  array{start: string, end: string, grace: int}  $dutyTimes
     */
    protected function calculateLateMinutes(
        CarbonInterface $checkIn,
        CarbonInterface $date,
        array $dutyTimes,
        string $timezone,
    ): int {
        $dutyStart = Carbon::parse(
            $date->toDateString().' '.$dutyTimes['start'],
            $timezone,
        );
        $graceEnd = $dutyStart->copy()->addMinutes($dutyTimes['grace']);

        if ($checkIn->lte($graceEnd)) {
            return 0;
        }

        return (int) $graceEnd->diffInMinutes($checkIn);
    }

    protected function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return "{$remainingMinutes}m";
        }

        if ($remainingMinutes === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$remainingMinutes}m";
    }
}
