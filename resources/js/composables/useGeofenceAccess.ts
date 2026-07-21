import { computed, ref, type Ref } from 'vue';

export type GeofenceSite = {
    id: number;
    name: string;
    require_public_ip: boolean;
    allowed_public_ips: string[];
    public_ip_allowed: boolean | null;
    radius_meters?: number;
};

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
    const coords = ref<{ latitude: number; longitude: number; accuracy: number | null } | null>(null);
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

    const gpsReady = computed(() => Boolean(coords.value) && !locating.value);
    const wifiReady = computed(() => !networkBlocked.value);
    const isReady = computed(() => gpsReady.value && wifiReady.value);

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
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 10000 },
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
        gpsReady,
        wifiReady,
        isReady,
        refreshPublicIp,
        refreshLocation,
        refreshAll,
    };
}
