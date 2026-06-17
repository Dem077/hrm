<?php

namespace App\Services\Attendance;

use App\Models\DutyRoster;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DutyRosterAssignmentService
{
    public const MAX_DAYS = 31;

    /**
     * @param  list<int>  $employeeIds
     * @param  list<string>  $dates
     * @param  array{start: string, end: string, grace: int}  $dutyTimes
     */
    public function assignToDates(
        array $employeeIds,
        array $dates,
        array $dutyTimes,
        ?string $notes = null,
    ): int {
        $dates = $this->normalizeDates($dates);

        if ($dates === [] || $employeeIds === []) {
            return 0;
        }

        $assigned = 0;

        foreach ($employeeIds as $employeeId) {
            foreach ($dates as $date) {
                DutyRoster::query()->updateOrCreate(
                    [
                        'employee_id' => $employeeId,
                        'duty_date' => $date,
                    ],
                    [
                        'duty_start_time' => $dutyTimes['start'],
                        'duty_end_time' => $dutyTimes['end'],
                        'grace_minutes' => $dutyTimes['grace'],
                        'notes' => $notes,
                    ],
                );

                $assigned++;
            }
        }

        return $assigned;
    }

    /**
     * @param  list<int>  $employeeIds
     * @param  array{start: string, end: string, grace: int}  $dutyTimes
     */
    public function bulkAssign(
        array $employeeIds,
        CarbonInterface $from,
        CarbonInterface $to,
        array $dutyTimes,
        ?string $notes = null,
        bool $skipWeekends = false,
    ): int {
        return $this->assignToDates(
            $employeeIds,
            $this->expandDateRange($from, $to, $skipWeekends),
            $dutyTimes,
            $notes,
        );
    }

    /**
     * @return list<string>
     */
    public function expandDateRange(CarbonInterface $from, CarbonInterface $to, bool $skipWeekends = false): array
    {
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $to = $from->copy()->addDays(self::MAX_DAYS);
        }

        $dates = [];

        for ($date = Carbon::parse($from); $date->lte($to); $date = $date->addDay()) {
            if ($skipWeekends && $this->isWeekend($date)) {
                continue;
            }

            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    /**
     * @param  list<string>  $dates
     * @return list<string>
     */
    public function normalizeDates(array $dates): array
    {
        return collect($dates)
            ->map(fn (string $date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->take(self::MAX_DAYS)
            ->all();
    }

    protected function isWeekend(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true);
    }
}
