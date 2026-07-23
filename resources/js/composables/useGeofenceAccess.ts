import { computed, ref, type Ref } from 'vue';

export type GeofenceSite = {
    id: number;
    name: string;
    latitude?: number;
    longitude?: number;
    radius_meters?: number;
    max_accuracy_meters?: number;
    require_public_ip: boolean;
    allowed_public_ips: string[];
    public_ip_allowed: boolean | null;
};

export type GeofenceCoords = {
    latitude: number;
    longitude: number;
    accuracy: number | null;
};

/** Haversine distance in meters — mirrors HasGeofenceSiteRules::distanceMetersFrom. */
export function distanceMetersBetween(
    fromLat: number,
    fromLng: number,
    toLat: number,
    toLng: number,
): number {
    const earthRadius = 6_371_000;
    const latFrom = (fromLat * Math.PI) / 180;
    const latTo = (toLat * Math.PI) / 180;
    const latDelta = ((toLat - fromLat) * Math.PI) / 180;
    const lonDelta = ((toLng - fromLng) * Math.PI) / 180;

    const a =
        Math.sin(latDelta / 2) ** 2 +
        Math.cos(latFrom) * Math.cos(latTo) * Math.sin(lonDelta / 2) ** 2;

    return 2 * earthRadius * Math.asin(Math.min(1, Math.sqrt(a)));
}

/** Mirrors HasGeofenceSiteRules::containsCoordinates — radius only, no accuracy buffer. */
export function siteContainsCoordinates(
    site: Pick<GeofenceSite, 'latitude' | 'longitude' | 'radius_meters' | 'max_accuracy_meters'>,
    latitude: number,
    longitude: number,
    _accuracyMeters: number | null = null,
): boolean {
    if (
        site.latitude === undefined ||
        site.longitude === undefined ||
        site.radius_meters === undefined
    ) {
        return false;
    }

    const distance = distanceMetersBetween(site.latitude, site.longitude, latitude, longitude);

    return distance <= site.radius_meters;
}

function ipv4ToInt(ip: string): number | null {
    const parts = ip.split('.').map((part) => Number(part));
    if (parts.length !== 4 || parts.some((part) => !Number.isInteger(part) || part < 0 || part > 255)) {
        return null;
    }

    return ((parts[0]! << 24) | (parts[1]! << 16) | (parts[2]! << 8) | parts[3]!) >>> 0;
}

export function ipMatchesAllowList(ip: string | null, allowed: string[]): boolean {
    if (!ip || allowed.length === 0) {
        return false;
    }

    return allowed.some((entry) => {
        const rule = entry.trim();
        if (!rule) {
            return false;
        }

        if (!rule.includes('/')) {
            return rule === ip;
        }

        const [range, bitsRaw] = rule.split('/');
        const bits = Number(bitsRaw);
        if (!range || !Number.isFinite(bits) || bits < 0 || bits > 32) {
            return false;
        }

        const ipNum = ipv4ToInt(ip);
        const rangeNum = ipv4ToInt(range);
        if (ipNum === null || rangeNum === null) {
            return false;
        }

        const mask = bits === 0 ? 0 : (0xffffffff << (32 - bits)) >>> 0;
        return (ipNum & mask) === (rangeNum & mask);
    });
}

export function useGeofenceAccess(selectedSite: Ref<GeofenceSite | null | undefined>) {
    const coords = ref<GeofenceCoords | null>(null);
    const locating = ref(false);
    const publicIp = ref<string | null>(null);
    const publicIpChecking = ref(true);
    const publicIpError = ref('');

    const networkBlocked = computed(() => {
        const site = selectedSite.value;
        if (!site?.require_public_ip) {
            return false;
        }

        if (site.public_ip_allowed === true) {
            return false;
        }

        if (publicIp.value && ipMatchesAllowList(publicIp.value, site.allowed_public_ips ?? [])) {
            return false;
        }

        if (publicIpChecking.value) {
            return true;
        }

        return true;
    });

    const distanceMeters = computed(() => {
        const site = selectedSite.value;
        const position = coords.value;

        if (
            !site ||
            !position ||
            site.latitude === undefined ||
            site.longitude === undefined
        ) {
            return null;
        }

        return distanceMetersBetween(site.latitude, site.longitude, position.latitude, position.longitude);
    });

    const withinFence = computed(() => {
        const site = selectedSite.value;
        const position = coords.value;

        if (!site || !position) {
            return false;
        }

        return siteContainsCoordinates(site, position.latitude, position.longitude, position.accuracy);
    });

    const accuracyTooLow = computed(() => {
        const site = selectedSite.value;
        const position = coords.value;

        if (!site || !position || position.accuracy === null || site.max_accuracy_meters === undefined) {
            return false;
        }

        return position.accuracy > site.max_accuracy_meters;
    });

    const gpsReady = computed(() => Boolean(coords.value) && !locating.value);
    const wifiReady = computed(() => !networkBlocked.value);
    const isReady = computed(
        () => gpsReady.value && wifiReady.value && withinFence.value && !accuracyTooLow.value,
    );

    async function refreshPublicIp() {
        publicIpChecking.value = true;
        publicIpError.value = '';

        try {
            const response = await fetch('https://api.ipify.org?format=json', {
                signal: AbortSignal.timeout(8000),
            });

            if (!response.ok) {
                throw new Error('Lookup failed');
            }

            const data = (await response.json()) as { ip?: string };
            if (!data.ip) {
                throw new Error('No IP returned');
            }

            publicIp.value = data.ip;
        } catch {
            publicIp.value = null;
            publicIpError.value = 'Could not verify office Wi‑Fi. Try again.';
        } finally {
            publicIpChecking.value = false;
        }
    }

    function refreshLocation() {
        if (!navigator.geolocation) {
            return;
        }

        locating.value = true;

        navigator.geolocation.getCurrentPosition(
            (position) => {
                coords.value = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy ?? null,
                };
                locating.value = false;
            },
            () => {
                coords.value = null;
                locating.value = false;
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
        );
    }

    function refreshAll() {
        refreshLocation();
        void refreshPublicIp();
    }

    return {
        coords,
        locating,
        publicIp,
        publicIpChecking,
        publicIpError,
        networkBlocked,
        distanceMeters,
        withinFence,
        accuracyTooLow,
        gpsReady,
        wifiReady,
        isReady,
        refreshPublicIp,
        refreshLocation,
        refreshAll,
    };
}
