<?php

namespace App\Enums;

enum PayrollLoanBank: string
{
    case Bml = 'BML';
    case Mib = 'MIB';
    case Cbm = 'CBM';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $bank) => ['value' => $bank->value, 'label' => $bank->label()],
            self::cases(),
        );
    }
}
