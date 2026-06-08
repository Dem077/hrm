<?php

namespace App\Enums;

enum AttendanceMachineBrand: string
{
    case Zkt = 'zkt';
    case HikVision = 'hikvision';

    public function label(): string
    {
        return match ($this) {
            self::Zkt => 'ZKT',
            self::HikVision => 'HikVision',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $brand) => [
                'value' => $brand->value,
                'label' => $brand->label(),
            ])
            ->values()
            ->all();
    }
}
