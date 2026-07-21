<?php

namespace App\Support\Concerns;

use App\Support\PublicIp;

trait HasGeofenceSiteRules
{
    /**
     * @return list<string>
     */
    public function allowedPublicIpList(): array
    {
        return collect($this->allowed_public_ips ?? [])
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()
            ->values()
            ->all();
    }

    public function clientIpIsAllowed(?string $clientIp): bool
    {
        if (! $this->require_public_ip) {
            return true;
        }

        return PublicIp::matchesAllowList($clientIp, $this->allowedPublicIpList());
    }

    public function distanceMetersFrom(float $latitude, float $longitude): float
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($this->latitude);
        $latTo = deg2rad($latitude);
        $latDelta = deg2rad($latitude - $this->latitude);
        $lonDelta = deg2rad($longitude - $this->longitude);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    public function containsCoordinates(float $latitude, float $longitude, ?float $accuracyMeters = null): bool
    {
        $distance = $this->distanceMetersFrom($latitude, $longitude);

        $buffer = 0.0;
        if ($accuracyMeters !== null && $accuracyMeters > 0) {
            $buffer = min($accuracyMeters, (float) $this->max_accuracy_meters);
        }

        return $distance <= ($this->radius_meters + $buffer);
    }
}
