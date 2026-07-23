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
        // Radius is enforced strictly against the configured circle.
        // GPS accuracy is validated separately (max_accuracy_meters) and must not
        // expand the fence — otherwise phones with ±100–150m readings accept punches
        // far outside the drawn radius.
        return $this->distanceMetersFrom($latitude, $longitude) <= (float) $this->radius_meters;
    }
}
