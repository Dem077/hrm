<?php

namespace App\Enums;

enum PayrollComponentCalculationMethod: string
{
    case Fixed = 'fixed';
    case Daily = 'daily';
    case Hourly = 'hourly';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::Daily => 'Attendance allowance (days attended)',
            self::Hourly => 'Attendance allowance (hours worked)',
        };
    }

    public function amountLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Amount',
            self::Daily => 'Rate / attended day',
            self::Hourly => 'Rate / hour',
        };
    }
}
