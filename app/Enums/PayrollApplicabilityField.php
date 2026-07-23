<?php

namespace App\Enums;

enum PayrollApplicabilityField: string
{
    case Nationality = 'nationality';
    case EmploymentType = 'employment_type';

    public function label(): string
    {
        return match ($this) {
            self::Nationality => 'Nationality',
            self::EmploymentType => 'Employment type',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $field) => [
                'value' => $field->value,
                'label' => $field->label(),
            ])
            ->values()
            ->all();
    }
}
