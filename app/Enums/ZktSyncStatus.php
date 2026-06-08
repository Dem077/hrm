<?php

namespace App\Enums;

enum ZktSyncStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'warning',
            self::Success => 'success',
            self::Failed => 'danger',
        };
    }
}
