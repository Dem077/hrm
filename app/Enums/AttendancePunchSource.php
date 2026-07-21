<?php

namespace App\Enums;

enum AttendancePunchSource: string
{
    case Device = 'device';
    case AttendanceSheet = 'attendance_sheet';
    case SelfApp = 'self_app';

    public function label(): string
    {
        return match ($this) {
            self::Device => 'Device',
            self::AttendanceSheet => 'Attendance sheet',
            self::SelfApp => 'Mobile punch',
        };
    }
}
