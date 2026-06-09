<?php

namespace App\Enums;

enum PayrollComponentType: string
{
    case Addition = 'addition';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Addition => 'Addition',
            self::Deduction => 'Deduction',
        };
    }
}
