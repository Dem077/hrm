<?php

namespace App\Enums;

enum ZktConnectionStatus: string
{
    case Unknown = 'unknown';
    case Online = 'online';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::Online => 'Online',
            self::Offline => 'Offline',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unknown => 'gray',
            self::Online => 'success',
            self::Offline => 'danger',
        };
    }

    public static function resolve(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            foreach (self::cases() as $case) {
                if ($case->value === strtolower($value)) {
                    return $case;
                }

                if (strcasecmp($case->label(), $value) === 0) {
                    return $case;
                }
            }
        }

        return self::Unknown;
    }
}
