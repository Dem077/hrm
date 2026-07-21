<?php

namespace App\Enums;

enum ZktConnectionMode: string
{
    case TcpPull = 'tcp_pull';
    case AdmsPush = 'adms_push';

    public function label(): string
    {
        return match ($this) {
            self::TcpPull => 'Local TCP',
            self::AdmsPush => 'ADMS (internet)',
        };
    }

    public function isAdms(): bool
    {
        return $this === self::AdmsPush;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ])
            ->values()
            ->all();
    }
}
