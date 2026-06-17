<?php

namespace App\Enums;

enum DutyType: string
{
    case Normal = 'normal';
    case Shift = 'shift';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal Duty',
            self::Shift => 'Shift Duty',
        };
    }

    public function isShift(): bool
    {
        return $this === self::Shift;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
