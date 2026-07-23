<?php

namespace App\Enums;

enum PayrollApplicabilityOperator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'Equals',
            self::NotEquals => 'Does not equal',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Equals => '=',
            self::NotEquals => '≠',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $operator) => [
                'value' => $operator->value,
                'label' => $operator->label(),
            ])
            ->values()
            ->all();
    }
}
