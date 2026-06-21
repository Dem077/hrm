<?php

namespace App\Enums;

enum ZktMachineType: string
{
    case Attendance = 'attendance';
    case Access = 'access';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Attendance machine',
            self::Access => 'Access machine',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Attendance => 'Attendance',
            self::Access => 'Access',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Attendance => 'info',
            self::Access => 'gray',
        };
    }

    public function countsForAttendanceSheet(): bool
    {
        return $this === self::Attendance;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])
            ->values()
            ->all();
    }
}
