<?php

namespace App\Enums;

enum AttendanceDayStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Incomplete = 'incomplete';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::Incomplete => 'Incomplete',
            self::Holiday => 'Holiday',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
            self::Incomplete => 'warning',
            self::Holiday => 'gray',
        };
    }
}
