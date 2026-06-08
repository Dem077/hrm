<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable([
    'effective_from',
    'duty_start_time',
    'duty_end_time',
    'grace_minutes',
    'saturday_duty_start_time',
    'saturday_duty_end_time',
    'saturday_grace_minutes',
])]
class AttendanceDutyPolicy extends Model
{
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'grace_minutes' => 'integer',
            'saturday_grace_minutes' => 'integer',
        ];
    }

    public static function ensureDefault(): self
    {
        return static::query()->firstOrCreate(
            ['effective_from' => '2020-01-01'],
            [
                'duty_start_time' => '09:00:00',
                'duty_end_time' => '18:00:00',
                'grace_minutes' => 15,
                'saturday_duty_start_time' => '09:00:00',
                'saturday_duty_end_time' => '14:00:00',
                'saturday_grace_minutes' => 15,
            ],
        );
    }

    /**
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        static::ensureDefault();

        return static::query()->orderBy('effective_from')->get();
    }

    public static function forDate(CarbonInterface $date, ?Collection $policies = null): self
    {
        $policies ??= static::ordered();

        $match = $policies
            ->filter(fn (self $policy) => $policy->effective_from->toDateString() <= $date->toDateString())
            ->sortByDesc(fn (self $policy) => $policy->effective_from->toDateString())
            ->first();

        return $match ?? static::ensureDefault();
    }

    /**
     * @return array{start: string, end: string, grace: int}
     */
    public function resolveDutyTimes(CarbonInterface $date, Employee $employee): array
    {
        if ($date->dayOfWeek === Carbon::SATURDAY && $employee->works_saturday) {
            return [
                'start' => (string) ($this->saturday_duty_start_time ?? $this->duty_start_time),
                'end' => (string) ($this->saturday_duty_end_time ?? $this->duty_end_time),
                'grace' => (int) ($this->saturday_grace_minutes ?? $this->grace_minutes),
            ];
        }

        return [
            'start' => (string) $this->duty_start_time,
            'end' => (string) $this->duty_end_time,
            'grace' => (int) $this->grace_minutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        return [
            'id' => $this->id,
            'effective_from' => $this->effective_from?->toDateString(),
            'duty_start_time' => $this->formatTimeForInput($this->duty_start_time),
            'duty_end_time' => $this->formatTimeForInput($this->duty_end_time),
            'grace_minutes' => $this->grace_minutes,
            'saturday_duty_start_time' => $this->formatTimeForInput($this->saturday_duty_start_time),
            'saturday_duty_end_time' => $this->formatTimeForInput($this->saturday_duty_end_time),
            'saturday_grace_minutes' => $this->saturday_grace_minutes ?? $this->grace_minutes,
            'label' => $this->formatTimeForInput($this->duty_start_time)
                .'–'.$this->formatTimeForInput($this->duty_end_time)
                .' · '.$this->grace_minutes.'m grace',
        ];
    }

    protected function formatTimeForInput(?string $time): string
    {
        if (! $time) {
            return '09:00';
        }

        return substr($time, 0, 5);
    }
}
