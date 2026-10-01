<?php

namespace App\Enums;

enum PayrollBuildStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Idle',
            self::Queued => 'Queued',
            self::Running => 'Building',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running], true);
    }
}
