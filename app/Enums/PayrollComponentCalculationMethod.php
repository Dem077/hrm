<?php

namespace App\Enums;

enum PayrollComponentCalculationMethod: string
{
    case Fixed = 'fixed';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::Daily => 'Per day worked',
        };
    }

    public function amountLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Amount',
            self::Daily => 'Rate / day',
        };
    }
}
