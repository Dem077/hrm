<?php

namespace App\Enums;

enum BloodGroup: string
{
    case APositive = 'a_positive';
    case ANegative = 'a_negative';
    case BPositive = 'b_positive';
    case BNegative = 'b_negative';
    case AbPositive = 'ab_positive';
    case AbNegative = 'ab_negative';
    case OPositive = 'o_positive';
    case ONegative = 'o_negative';

    public function label(): string
    {
        return match ($this) {
            self::APositive => 'A+',
            self::ANegative => 'A-',
            self::BPositive => 'B+',
            self::BNegative => 'B-',
            self::AbPositive => 'AB+',
            self::AbNegative => 'AB-',
            self::OPositive => 'O+',
            self::ONegative => 'O-',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $group) => [
                'value' => $group->value,
                'label' => $group->label(),
            ])
            ->values()
            ->all();
    }
}
