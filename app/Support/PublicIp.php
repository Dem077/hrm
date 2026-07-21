<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\IpUtils;

class PublicIp
{
    /**
     * @var list<string>
     */
    private const PRIVATE_RANGES = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        '::1',
        'fc00::/7',
        'fe80::/10',
        '::ffff:127.0.0.0/104',
        '::ffff:10.0.0.0/104',
        '::ffff:172.16.0.0/108',
        '::ffff:192.168.0.0/112',
    ];

    public static function isValid(?string $ip): bool
    {
        return filled($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public static function isPublic(?string $ip): bool
    {
        if (! self::isValid($ip)) {
            return false;
        }

        return ! IpUtils::checkIp($ip, self::PRIVATE_RANGES);
    }

    public static function isPrivate(?string $ip): bool
    {
        return self::isValid($ip) && ! self::isPublic($ip);
    }

    /**
     * Prefer the request IP when it is already public (cloud-hosted app).
     * When the app is reached over LAN/localhost, fall back to the browser-reported egress IP.
     */
    public static function resolve(?string $requestIp, ?string $reportedPublicIp = null): ?string
    {
        if (self::isPublic($requestIp)) {
            return $requestIp;
        }

        if (self::isPublic($reportedPublicIp)) {
            return $reportedPublicIp;
        }

        return filled($requestIp) ? $requestIp : (filled($reportedPublicIp) ? $reportedPublicIp : null);
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function matchesAllowList(?string $ip, array $allowed): bool
    {
        if (! self::isValid($ip) || $allowed === []) {
            return false;
        }

        $normalizedAllowed = collect($allowed)
            ->map(fn ($entry) => trim((string) $entry))
            ->filter()
            ->values()
            ->all();

        if ($normalizedAllowed === []) {
            return false;
        }

        return IpUtils::checkIp($ip, $normalizedAllowed);
    }
}
