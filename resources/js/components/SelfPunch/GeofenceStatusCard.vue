<script setup lang="ts">
import { computed } from 'vue';

import type { GeofenceSite } from '@/composables/useGeofenceAccess';

type StatusTone = 'loading' | 'ready' | 'error' | 'warn';

const props = defineProps<{
    selectedSite: GeofenceSite | null;
    gpsReady: boolean;
    wifiReady: boolean;
    withinFence: boolean;
    accuracyTooLow: boolean;
    locating: boolean;
    publicIpChecking: boolean;
    networkBlocked: boolean;
    distanceMeters: number | null;
    coords: { latitude: number; longitude: number; accuracy: number | null } | null;
    readyLabel: string;
    notReadyMessages?: {
        locating?: string;
        wifi?: string;
        gps?: string;
        fence?: string;
        accuracy?: string;
    };
}>();

const gpsStatus = computed<{ tone: StatusTone; label: string }>(() => {
    if (props.locating) {
        return { tone: 'loading', label: 'Finding your location…' };
    }

    if (!props.coords) {
        return { tone: 'error', label: 'Location unavailable' };
    }

    const accuracyLabel = `±${Math.round(props.coords.accuracy ?? 0)}m`;

    if (props.accuracyTooLow) {
        const maxAccuracy = props.selectedSite?.max_accuracy_meters ?? 0;
        return {
            tone: 'error',
            label: `GPS too inaccurate (${accuracyLabel}). Need ≤${maxAccuracy}m`,
        };
    }

    if (!props.withinFence) {
        const distance = props.distanceMeters !== null ? Math.round(props.distanceMeters) : null;
        const radius = props.selectedSite?.radius_meters ?? null;

        if (distance !== null && radius !== null) {
            return {
                tone: 'warn',
                label: `Outside site — ${distance}m away (radius ${radius}m)`,
            };
        }

        return { tone: 'warn', label: 'Outside the site geofence' };
    }

    const distance = props.distanceMeters !== null ? Math.round(props.distanceMeters) : null;
    const radius = props.selectedSite?.radius_meters ?? null;

    if (distance !== null && radius !== null) {
        return { tone: 'ready', label: `Inside site — ${distance}m of ${radius}m (${accuracyLabel})` };
    }

    return { tone: 'ready', label: `Inside site (${accuracyLabel})` };
});

const wifiStatus = computed<{ tone: StatusTone; label: string; hidden: boolean }>(() => {
    if (!props.selectedSite?.require_public_ip) {
        return { tone: 'ready', label: 'Network check not required', hidden: true };
    }

    if (props.publicIpChecking) {
        return { tone: 'loading', label: 'Checking office Wi‑Fi…', hidden: false };
    }

    if (props.networkBlocked) {
        return { tone: 'error', label: 'Connect to office Wi‑Fi', hidden: false };
    }

    return { tone: 'ready', label: 'Office Wi‑Fi verified', hidden: false };
});

const statusMessage = computed(() => {
    const messages = props.notReadyMessages ?? {};

    if (!props.gpsReady && props.locating) {
        return messages.locating ?? 'Getting your location…';
    }

    if (props.networkBlocked && props.selectedSite?.require_public_ip) {
        return messages.wifi ?? 'You must be connected to the office Wi‑Fi';
    }

    if (!props.gpsReady) {
        return messages.gps ?? 'Allow location access to continue';
    }

    if (props.accuracyTooLow) {
        return messages.accuracy ?? 'GPS accuracy is too low — move outdoors or wait for a better signal';
    }

    if (!props.withinFence) {
        const distance = props.distanceMeters !== null ? Math.round(props.distanceMeters) : null;
        const radius = props.selectedSite?.radius_meters ?? null;

        if (messages.fence) {
            return messages.fence;
        }

        if (distance !== null && radius !== null) {
            return `About ${distance}m from ${props.selectedSite?.name ?? 'the site'} — move within ${radius}m to continue`;
        }

        return 'Move closer to the site to continue';
    }

    return props.readyLabel;
});

const isReady = computed(
    () => props.gpsReady && props.wifiReady && props.withinFence && !props.accuracyTooLow,
);
</script>

<template>
    <div class="self-punch-card overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-surface-elevated dark:shadow-black/20">
        <div
            class="px-5 py-4 transition-colors duration-500"
            :class="
                isReady
                    ? 'bg-gradient-to-r from-emerald-500/10 to-brand-500/10 dark:from-emerald-500/20 dark:to-brand-500/15'
                    : 'bg-slate-50 dark:bg-surface-muted'
            "
        >
            <p
                class="text-center text-sm font-semibold transition-colors duration-300"
                :class="isReady ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400'"
            >
                {{ statusMessage }}
            </p>
        </div>

        <div class="space-y-3 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <span
                    class="status-dot h-2.5 w-2.5 shrink-0 rounded-full"
                    :class="{
                        'status-dot--loading': gpsStatus.tone === 'loading',
                        'bg-emerald-500': gpsStatus.tone === 'ready',
                        'bg-amber-500': gpsStatus.tone === 'warn',
                        'bg-red-500': gpsStatus.tone === 'error',
                    }"
                />
                <span class="text-sm text-slate-700 dark:text-slate-300">{{ gpsStatus.label }}</span>
            </div>
            <div v-if="!wifiStatus.hidden" class="flex items-center gap-3">
                <span
                    class="status-dot h-2.5 w-2.5 shrink-0 rounded-full"
                    :class="{
                        'status-dot--loading': wifiStatus.tone === 'loading',
                        'bg-emerald-500': wifiStatus.tone === 'ready',
                        'bg-red-500': wifiStatus.tone === 'error',
                    }"
                />
                <span class="text-sm text-slate-700 dark:text-slate-300">{{ wifiStatus.label }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.status-dot--loading {
    background-color: rgb(245 158 11);
    animation: status-pulse 1.2s ease-in-out infinite;
}

@keyframes status-pulse {
    0%,
    100% {
        opacity: 1;
        transform: scale(1);
    }

    50% {
        opacity: 0.5;
        transform: scale(1.2);
    }
}
</style>
