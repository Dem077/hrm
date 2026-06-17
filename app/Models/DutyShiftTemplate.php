<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'duty_start_time',
    'duty_end_time',
    'grace_minutes',
    'notes',
    'is_active',
    'sort_order',
])]
class DutyShiftTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return array{start: string, end: string, grace: int}
     */
    public function resolveDutyTimes(): array
    {
        return [
            'start' => (string) $this->duty_start_time,
            'end' => (string) $this->duty_end_time,
            'grace' => (int) ($this->grace_minutes ?? 15),
        ];
    }

    public function formatTimeForInput(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return substr($time, 0, 5);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentationArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'duty_start_time' => $this->formatTimeForInput($this->duty_start_time),
            'duty_end_time' => $this->formatTimeForInput($this->duty_end_time),
            'grace_minutes' => $this->grace_minutes,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
