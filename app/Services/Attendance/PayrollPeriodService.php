<?php

namespace App\Services\Attendance;

use App\Models\AttendanceGeneralSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class PayrollPeriodService
{
    public function startDay(): int
    {
        return AttendanceGeneralSetting::current()->payroll_period_start_day;
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function currentPeriod(?CarbonInterface $referenceDate = null): array
    {
        return $this->periodContaining($referenceDate ?? now(config('app.timezone', 'UTC')));
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function previousPeriod(?CarbonInterface $referenceDate = null): array
    {
        $current = $this->currentPeriod($referenceDate);

        return $this->periodContaining($current['from']->copy()->subDay());
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function periodContaining(CarbonInterface $date): array
    {
        $timezone = config('app.timezone', 'UTC');
        $date = Carbon::parse($date->toDateTimeString(), $timezone)->startOfDay();
        $startDay = $this->startDay();

        if ($date->day >= $startDay) {
            $from = $this->setDayOfMonth($date->copy()->startOfMonth(), $startDay);
        } else {
            $from = $this->setDayOfMonth($date->copy()->subMonthNoOverflow()->startOfMonth(), $startDay);
        }

        $to = $from->copy()->addMonthNoOverflow()->subDay()->startOfDay();

        return [
            'from' => $from->startOfDay(),
            'to' => $to,
            'label' => $this->formatLabel($from, $to),
        ];
    }

    /**
     * @return list<array{offset: int, is_current: bool, from: string, to: string, label: string}>
     */
    public function recentPeriods(int $count = 6, ?CarbonInterface $referenceDate = null): array
    {
        $reference = Carbon::parse(
            ($referenceDate ?? now(config('app.timezone', 'UTC')))->toDateTimeString(),
            config('app.timezone', 'UTC'),
        )->startOfDay();

        $periods = [];

        for ($offset = 0; $offset < $count; $offset++) {
            $period = $this->periodContaining($reference);
            $periods[] = [
                'offset' => $offset,
                'is_current' => $offset === 0,
                ...$this->serializePeriod($period),
            ];

            $reference = $period['from']->copy()->subDay();
        }

        return $periods;
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string}|null
     */
    public function recentPeriodByOffset(int $offset, ?CarbonInterface $referenceDate = null): ?array
    {
        if ($offset < 0) {
            return null;
        }

        $period = $this->recentPeriods($offset + 1, $referenceDate)[$offset] ?? null;

        if ($period === null) {
            return null;
        }

        $timezone = config('app.timezone', 'UTC');

        return [
            'from' => Carbon::parse($period['from'], $timezone)->startOfDay(),
            'to' => Carbon::parse($period['to'], $timezone)->startOfDay(),
            'label' => $period['label'],
        ];
    }

    /**
     * @return array{
     *     start_day: int,
     *     end_day: int|null,
     *     recent: list<array{offset: int, is_current: bool, from: string, to: string, label: string}>,
     *     current: array{from: string, to: string, label: string},
     *     previous: array{from: string, to: string, label: string}
     * }
     */
    public function presentation(?CarbonInterface $referenceDate = null): array
    {
        $startDay = $this->startDay();
        $recent = $this->recentPeriods(6, $referenceDate);

        return [
            'start_day' => $startDay,
            'end_day' => $startDay > 1 ? $startDay - 1 : null,
            'recent' => $recent,
            'current' => $recent[0],
            'previous' => $recent[1],
        ];
    }

    protected function setDayOfMonth(Carbon $date, int $day): Carbon
    {
        $day = min($day, $date->daysInMonth);

        return $date->copy()->day($day);
    }

    protected function formatLabel(Carbon $from, Carbon $to): string
    {
        return sprintf('%s – %s', $from->format('M j, Y'), $to->format('M j, Y'));
    }

    /**
     * @param  array{from: Carbon, to: Carbon, label: string}  $period
     * @return array{from: string, to: string, label: string}
     */
    protected function serializePeriod(array $period): array
    {
        return [
            'from' => $period['from']->toDateString(),
            'to' => $period['to']->toDateString(),
            'label' => $period['label'],
        ];
    }
}
