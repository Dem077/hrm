<?php

namespace App\Enums;

enum ZktAdmsCommandStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Done => 'Done',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'info',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }
}
