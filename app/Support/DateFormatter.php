<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DateFormatter
{
    public const DATE = 'd/m/Y';

    public const DATETIME = 'd/m/Y H:i';

    public static function formatDate(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $date = $value instanceof CarbonInterface
            ? $value
            : Carbon::parse($value);

        return $date->format(self::DATE);
    }

    public static function formatDateTime(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $date = $value instanceof CarbonInterface
            ? $value
            : Carbon::parse($value);

        return $date->format(self::DATETIME);
    }

    public static function formatDateRange(CarbonInterface|string $from, CarbonInterface|string $to): string
    {
        return self::formatDate($from).' – '.self::formatDate($to);
    }
}
