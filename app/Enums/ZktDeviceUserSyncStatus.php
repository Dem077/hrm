<?php

namespace App\Enums;

enum ZktDeviceUserSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Synced => 'Synced',
            self::Failed => 'Failed',
            self::Removed => 'Removed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Synced => 'success',
            self::Failed => 'danger',
            self::Removed => 'gray',
        };
    }
}
