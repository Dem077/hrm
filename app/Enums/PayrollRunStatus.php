<?php

namespace App\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Processed = 'processed';
    case Finalised = 'finalised';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Processed => 'Processed',
            self::Finalised => 'Finalised',
        };
    }

    public function isEditable(): bool
    {
        return $this !== self::Finalised;
    }
}
