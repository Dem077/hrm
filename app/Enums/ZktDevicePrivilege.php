<?php

namespace App\Enums;

enum ZktDevicePrivilege: string
{
    case Employee = 'employee';
    case Enroller = 'enroller';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Enroller => 'Enroller',
            self::Administrator => 'Administrator',
        };
    }

    /**
     * ZKT device user role value passed to setUser().
     *
     * Mapped from this deployment's machine (staff 332/370/333):
     * Employee = 0, Enroller = 4, Administrator = 14.
     */
    public function deviceRole(): int
    {
        return match ($this) {
            self::Employee => 0,
            self::Enroller => 4,
            self::Administrator => 14,
        };
    }

    public static function tryFromDeviceRole(int $role): ?self
    {
        return match ($role) {
            0 => self::Employee,
            4 => self::Enroller,
            14 => self::Administrator,
            default => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $privilege) => [
                'value' => $privilege->value,
                'label' => $privilege->label(),
            ])
            ->values()
            ->all();
    }
}
