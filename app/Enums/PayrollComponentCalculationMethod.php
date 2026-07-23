<?php

namespace App\Enums;

enum PayrollComponentCalculationMethod: string
{
    case Fixed = 'fixed';
    case Daily = 'daily';
    case Hourly = 'hourly';
    case PerLateMinute = 'per_late_minute';
    case PerLateMinuteOfBasic = 'per_late_minute_of_basic';
    case PerAbsentDay = 'per_absent_day';
    case PerAbsentDayOfBasic = 'per_absent_day_of_basic';
    case CustomFormula = 'custom_formula';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::Daily => 'Attendance allowance (days attended)',
            self::Hourly => 'Attendance allowance (hours worked)',
            self::PerLateMinute => 'Fixed rate per late minute',
            self::PerLateMinuteOfBasic => '% of basic salary per late minute',
            self::PerAbsentDay => 'Fixed rate per absent day',
            self::PerAbsentDayOfBasic => '% of basic salary per absent day',
            self::CustomFormula => 'Custom formula',
        };
    }

    public function amountLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Amount',
            self::Daily => 'Rate / attended day',
            self::Hourly => 'Rate / hour',
            self::PerLateMinute => 'Rate / late minute',
            self::PerLateMinuteOfBasic => '% of basic salary / late minute',
            self::PerAbsentDay => 'Rate / absent day',
            self::PerAbsentDayOfBasic => '% of basic salary / absent day',
            self::CustomFormula => 'Formula',
        };
    }

    public function usesGlobalRate(): bool
    {
        return match ($this) {
            self::PerLateMinute,
            self::PerLateMinuteOfBasic,
            self::PerAbsentDay,
            self::PerAbsentDayOfBasic,
            self::CustomFormula => true,
            default => false,
        };
    }

    public function isPercentageOfBasicSalary(): bool
    {
        return match ($this) {
            self::PerLateMinuteOfBasic, self::PerAbsentDayOfBasic => true,
            default => false,
        };
    }

    public function isCustomFormula(): bool
    {
        return $this === self::CustomFormula;
    }

    public function isLateFineMethod(): bool
    {
        return match ($this) {
            self::PerLateMinute, self::PerLateMinuteOfBasic => true,
            default => false,
        };
    }

    public function isAbsentFeeMethod(): bool
    {
        return match ($this) {
            self::PerAbsentDay, self::PerAbsentDayOfBasic => true,
            default => false,
        };
    }

    public function isAttendanceAllowance(): bool
    {
        return match ($this) {
            self::Daily, self::Hourly => true,
            default => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function lateFineOptions(): array
    {
        return [self::PerLateMinute, self::PerLateMinuteOfBasic, self::CustomFormula];
    }

    /**
     * @return list<self>
     */
    public static function absentFeeOptions(): array
    {
        return [self::PerAbsentDay, self::PerAbsentDayOfBasic, self::CustomFormula];
    }

    /**
     * @return list<array{value: string, label: string, is_percentage_rate: bool, amount_label: string}>
     */
    public static function optionsPayload(array $methods): array
    {
        return array_map(fn (self $method) => [
            'value' => $method->value,
            'label' => $method->label(),
            'is_percentage_rate' => $method->isPercentageOfBasicSalary(),
            'amount_label' => $method->amountLabel(),
        ], $methods);
    }
}
