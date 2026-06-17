<?php

namespace App\Enums;

enum PayrollComponentType: string
{
    case Addition = 'addition';
    case Deduction = 'deduction';
    case Loan = 'loan';

    public function label(): string
    {
        return match ($this) {
            self::Addition => 'Addition',
            self::Deduction => 'Deduction',
            self::Loan => 'Loan',
        };
    }

    public function isLoan(): bool
    {
        return $this === self::Loan;
    }
}
