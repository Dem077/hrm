<?php

namespace App\Enums;

enum ZktConnectionProtocol: string
{
    case Tcp = 'tcp';
    case Udp = 'udp';

    public function label(): string
    {
        return match ($this) {
            self::Tcp => 'TCP (recommended)',
            self::Udp => 'UDP',
        };
    }
}
