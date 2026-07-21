<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'duty_date',
    'duty_start_time',
    'duty_end_time',
    'grace_minutes',
    'notes',
])]
class DutyRoster extends Model
{
    protected function casts(): array
    {
        return [
            'duty_date' => 'date',
            'grace_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
        $this->loadMissing('employee:id,staff_id,name,grade_id', 'employee.grade.level.node.group');

        $path = $this->employee?->grade?->resolvePath();

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'staff_id' => $this->employee->staff_id,
                'name' => $this->employee->name,
                'department' => $path['node']['name'] ?? $path['group']['name'] ?? null,
            ] : null,
            'duty_date' => $this->duty_date->toDateString(),
            'duty_start_time' => $this->formatTimeForInput($this->duty_start_time),
            'duty_end_time' => $this->formatTimeForInput($this->duty_end_time),
            'grace_minutes' => $this->grace_minutes,
            'notes' => $this->notes,
        ];
    }
}
