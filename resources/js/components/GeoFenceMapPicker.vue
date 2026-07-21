<script setup lang="ts">
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';

import type { Circle, Map as LeafletMap, Marker } from 'leaflet';

const props = withDefaults(
    defineProps<{
        latitude?: number | string | null;
        longitude?: number | string | null;
        radiusMeters?: number | string | null;
        error?: string;
    }>(),
    {
        latitude: null,
        longitude: null,
        radiusMeters: 100,
        error: undefined,
    },
);

const emit = defineEmits<{
    'update:latitude': [value: number];
    'update:longitude': [value: number];
    'update:radiusMeters': [value: number];
}>();

const mapEl = ref<HTMLElement | null>(null);
const searchQuery = ref('');
const searching = ref(false);
const searchError = ref('');
const locating = ref(false);

let map: LeafletMap | null = null;
let marker: Marker | null = null;
let circle: Circle | null = null;
let L: typeof import('leaflet') | null = null;

const parsedLat = computed(() => {
    const value = Number(props.latitude);
    return Number.isFinite(value) ? value : null;
});

const parsedLng = computed(() => {
    const value = Number(props.longitude);
    return Number.isFinite(value) ? value : null;
});

const parsedRadius = computed(() => {
    const value = Number(props.radiusMeters);
    return Number.isFinite(value) && value > 0 ? value : 100;
});

const hasPoint = computed(() => parsedLat.value !== null && parsedLng.value !== null);

function emitPoint(lat: number, lng: number) {
    emit('update:latitude', Number(lat.toFixed(7)));
    emit('update:longitude', Number(lng.toFixed(7)));
}

function ensureLayers(lat: number, lng: number) {
    if (!map || !L) {
        return;
    }

    if (!marker) {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', () => {
            const position = marker?.getLatLng();
            if (position) {
                emitPoint(position.lat, position.lng);
            }
        });
    } else {
        marker.setLatLng([lat, lng]);
    }

    if (!circle) {
        circle = L.circle([lat, lng], {
            radius: parsedRadius.value,
            color: '#d97706',
            fillColor: '#f59e0b',
            fillOpacity: 0.2,
            weight: 2,
        }).addTo(map);
    } else {
        circle.setLatLng([lat, lng]);
        circle.setRadius(parsedRadius.value);
    }
}

function fitToFence() {
    if (!map || !circle) {
        return;
    }

    map.fitBounds(circle.getBounds().pad(0.2), { animate: true, maxZoom: 17 });
}

function setPoint(lat: number, lng: number, fit = true) {
    emitPoint(lat, lng);
    ensureLayers(lat, lng);
    if (fit) {
        fitToFence();
    } else {
        map?.panTo([lat, lng]);
    }
}

async function initMap() {
    if (!mapEl.value || map) {
        return;
    }

    L = await import('leaflet');

    L.Marker.prototype.options.icon = L.icon({
        iconUrl: markerIcon,
        iconRetinaUrl: markerIcon2x,
        shadowUrl: markerShadow,
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41],
    });

    const startLat = parsedLat.value ?? 4.1755;
    const startLng = parsedLng.value ?? 73.5093;
    const zoom = hasPoint.value ? 16 : 12;

    map = L.map(mapEl.value, {
        zoomControl: true,
        attributionControl: true,
    }).setView([startLat, startLng], zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    if (hasPoint.value && parsedLat.value !== null && parsedLng.value !== null) {
        ensureLayers(parsedLat.value, parsedLng.value);
        fitToFence();
    }

    map.on('click', (event) => {
        setPoint(event.latlng.lat, event.latlng.lng, false);
    });

    requestAnimationFrame(() => map?.invalidateSize());
}

async function searchPlace() {
    const query = searchQuery.value.trim();
    if (query.length < 2) {
        searchError.value = 'Enter a place name or address to search.';
        return;
    }

    searching.value = true;
    searchError.value = '';

    try {
        const url = new URL('https://nominatim.openstreetmap.org/search');
        url.searchParams.set('q', query);
        url.searchParams.set('format', 'json');
        url.searchParams.set('limit', '1');

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Search failed');
        }

        const results = (await response.json()) as Array<{ lat: string; lon: string }>;
        const first = results[0];

        if (!first) {
            searchError.value = 'No places found. Try a more specific search.';
            return;
        }

        setPoint(Number(first.lat), Number(first.lon), true);
    } catch {
        searchError.value = 'Unable to search right now. Try again or click the map.';
    } finally {
        searching.value = false;
    }
}

function useCurrentLocation() {
    if (!navigator.geolocation) {
        searchError.value = 'Geolocation is not available in this browser.';
        return;
    }

    locating.value = true;
    searchError.value = '';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            setPoint(position.coords.latitude, position.coords.longitude, true);
            locating.value = false;
        },
        () => {
            searchError.value = 'Unable to read your current location.';
            locating.value = false;
        },
        { enableHighAccuracy: true, timeout: 15000 },
    );
}

watch(
    () => [parsedLat.value, parsedLng.value] as const,
    ([lat, lng]) => {
        if (lat === null || lng === null || !map) {
            return;
        }

        ensureLayers(lat, lng);
    },
);

watch(
    () => parsedRadius.value,
    (radius) => {
        if (circle) {
            circle.setRadius(radius);
            fitToFence();
        }
    },
);

onMounted(() => {
    void initMap();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    marker = null;
    circle = null;
    L = null;
});
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row">
            <input
                v-model="searchQuery"
                type="search"
                placeholder="Search place or address…"
                class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-100"
                @keydown.enter.prevent="searchPlace"
            />
            <div class="flex gap-2">
                <UiButton type="button" variant="secondary" :disabled="searching" @click="searchPlace">
                    {{ searching ? 'Searching…' : 'Search' }}
                </UiButton>
                <UiButton type="button" variant="ghost" :disabled="locating" @click="useCurrentLocation">
                    {{ locating ? 'Locating…' : 'My location' }}
                </UiButton>
            </div>
        </div>

        <div
            ref="mapEl"
            class="h-72 w-full overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700"
        />

        <p class="text-xs text-slate-500">
            Click the map to drop the pin, drag the marker to fine-tune, and adjust radius below. The amber circle is the punch zone.
        </p>

        <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Radius (meters)
                </label>
                <input
                    :value="parsedRadius"
                    type="range"
                    min="20"
                    max="5000"
                    step="10"
                    class="w-full accent-brand-600"
                    @input="emit('update:radiusMeters', Number(($event.target as HTMLInputElement).value))"
                />
            </div>
            <div class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                <span class="font-medium text-slate-800 dark:text-slate-100">{{ parsedRadius }}m</span>
            </div>
            <div class="text-xs text-slate-500 sm:text-right">
                <template v-if="hasPoint">
                    {{ parsedLat?.toFixed(5) }}, {{ parsedLng?.toFixed(5) }}
                </template>
                <template v-else>
                    No pin set yet
                </template>
            </div>
        </div>

        <p v-if="searchError" class="text-sm text-red-600 dark:text-red-400">{{ searchError }}</p>
        <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
