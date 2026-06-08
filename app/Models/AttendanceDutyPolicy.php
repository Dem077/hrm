<?php

namespace App\Models;

use App\Support\DateFormatter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable([
    'effective_from',
    'effective_until',
    'name',
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
            'effective_until' => 'date',
            'grace_minutes' => 'integer',
            'saturday_grace_minutes' => 'integer',
        ];
    }

    public static function ensureDefault(): self
    {
        return static::query()->firstOrCreate(
            [
                'effective_from' => '2020-01-01',
                'effective_until' => null,
            ],
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePermanent(Builder $query): Builder
    {
        return $query->whereNull('effective_until');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeTemporary(Builder $query): Builder
    {
        return $query->whereNotNull('effective_until');
    }

    /**
     * @return Collection<int, self>
     */
    public static function allOrdered(): Collection
    {
        static::ensureDefault();

        return static::query()->orderBy('effective_from')->get();
    }

    /**
     * @return Collection<int, self>
     */
    public static function permanentOrdered(): Collection
    {
        static::ensureDefault();

        return static::query()->permanent()->orderBy('effective_from')->get();
    }

    /**
     * @return Collection<int, self>
     */
    public static function temporaryOrdered(): Collection
    {
        return static::query()->temporary()->orderByDesc('effective_from')->get();
    }

    /**
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        return static::permanentOrdered();
    }

    public static function forDate(CarbonInterface $date, ?Collection $policies = null): self
    {
        $policies ??= static::allOrdered();
        $dateString = $date->toDateString();

        $temporary = $policies
            ->filter(fn (self $policy) => $policy->isTemporary()
                && $policy->effective_from->toDateString() <= $dateString
                && $policy->effective_until->toDateString() >= $dateString)
            ->sortByDesc(fn (self $policy) => $policy->effective_from->toDateString())
            ->first();

        if ($temporary) {
            return $temporary;
        }

        $permanent = $policies
            ->filter(fn (self $policy) => ! $policy->isTemporary()
                && $policy->effective_from->toDateString() <= $dateString)
            ->sortByDesc(fn (self $policy) => $policy->effective_from->toDateString())
            ->first();

        return $permanent ?? static::ensureDefault();
    }

    public function isTemporary(): bool
    {
        return $this->effective_until !== null;
    }

    public function coversDate(CarbonInterface $date): bool
    {
        $dateString = $date->toDateString();

        if ($this->isTemporary()) {
            return $this->effective_from->toDateString() <= $dateString
                && $this->effective_until->toDateString() >= $dateString;
        }

        return $this->effective_from->toDateString() <= $dateString;
    }

    public static function overlapsTemporaryPeriod(string $from, string $until, ?int $ignoreId = null): bool
    {
        $query = static::query()
            ->temporary()
            ->where('effective_from', '<=', $until)
            ->where('effective_until', '>=', $from);

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
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
            'name' => $this->name,
            'is_temporary' => $this->isTemporary(),
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_until' => $this->effective_until?->toDateString(),
            'period_label' => $this->isTemporary()
                ? DateFormatter::formatDateRange($this->effective_from, $this->effective_until)
                : null,
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
