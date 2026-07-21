<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import GeofenceStatusCard from '@/components/SelfPunch/GeofenceStatusCard.vue';
import UiModal from '@/components/ui/UiModal.vue';
import { useGeofenceAccess, type GeofenceSite } from '@/composables/useGeofenceAccess';
import { useLiveClock } from '@/composables/useLiveClock';
import { usePunchDeviceId } from '@/composables/usePunchDeviceId';
import AppLayout from '@/layouts/AppLayout.vue';

type PunchSite = GeofenceSite & {
    code: string | null;
    latitude: number;
    longitude: number;
    max_accuracy_meters: number;
};

type DoorSite = GeofenceSite & {
    code: string | null;
    latitude: number;
    longitude: number;
    max_accuracy_meters: number;
    device: { id: number; name: string; connection_mode: string | null };
};

const props = defineProps<{
    employee: { id: number; name: string; staff_id: string } | null;
    sites: PunchSite[];
    doorSites: DoorSite[];
    todaysPunches: Array<{
        id: number;
        punch_state: number;
        punch_state_label: string;
        punched_at: string;
        site_name: string | null;
    }>;
    todaysDoorOpens: Array<{
        id: number;
        site_name: string | null;
        device_name: string | null;
        status: string;
        opened_at: string;
    }>;
    doorOpenCooldownSeconds: number;
    doorOpenCooldownRemaining: number;
    clientIp: string | null;
}>();

const { time, date, timezoneLabel } = useLiveClock();
const { deviceId } = usePunchDeviceId();

const activeTab = ref<'punch' | 'door'>(props.sites.length > 0 ? 'punch' : 'door');
const selectedPunchSiteId = ref<number | null>(props.sites[0]?.id ?? null);
const selectedDoorSiteId = ref<number | null>(props.doorSites[0]?.id ?? null);
const confirmOpen = ref(false);
const pendingPunchState = ref<0 | 1 | null>(null);
const doorCooldownRemaining = ref(Math.max(0, props.doorOpenCooldownRemaining));
let doorCooldownTimer: ReturnType<typeof setInterval> | null = null;

const selectedPunchSite = computed(() => props.sites.find((site) => site.id === selectedPunchSiteId.value) ?? null);
const selectedDoorSite = computed(() => props.doorSites.find((site) => site.id === selectedDoorSiteId.value) ?? null);

const activeGeofenceSite = computed<GeofenceSite | null>(() =>
    activeTab.value === 'punch' ? selectedPunchSite.value : selectedDoorSite.value,
);

const geofence = useGeofenceAccess(activeGeofenceSite);
const {
    coords,
    locating,
    publicIp,
    publicIpChecking,
    publicIpError,
    networkBlocked,
    gpsReady,
    wifiReady,
    isReady,
    refreshAll,
} = geofence;

const punchForm = useForm({
    self_punch_site_id: selectedPunchSiteId.value,
    punch_state: 0 as 0 | 1,
    latitude: null as number | null,
    longitude: null as number | null,
    accuracy_meters: null as number | null,
    public_ip: null as string | null,
    device_id: '' as string,
});

const doorForm = useForm({
    remote_door_site_id: selectedDoorSiteId.value,
    latitude: null as number | null,
    longitude: null as number | null,
    accuracy_meters: null as number | null,
    public_ip: null as string | null,
});

const canPunch = computed(() => isReady.value && Boolean(deviceId.value) && !punchForm.processing);
const canOpenDoor = computed(() => isReady.value && !doorForm.processing && doorCooldownRemaining.value === 0);
const doorButtonLabel = computed(() => {
    if (doorForm.processing) {
        return 'Sending…';
    }

    if (doorCooldownRemaining.value > 0) {
        return `Wait ${doorCooldownRemaining.value}s`;
    }

    return 'Open door';
});
const showTabs = computed(() => props.sites.length > 0 && props.doorSites.length > 0);

const pendingPunchLabel = computed(() => (pendingPunchState.value === 1 ? 'Check out' : 'Check in'));
const lastPunch = computed(() => props.todaysPunches[0] ?? null);
const suggestedAction = computed<0 | 1>(() => (lastPunch.value?.punch_state === 0 ? 1 : 0));

watch(coords, (value) => {
    if (!value) {
        return;
    }

    punchForm.latitude = value.latitude;
    punchForm.longitude = value.longitude;
    punchForm.accuracy_meters = value.accuracy;
    doorForm.latitude = value.latitude;
    doorForm.longitude = value.longitude;
    doorForm.accuracy_meters = value.accuracy;
});

watch(publicIp, (value) => {
    punchForm.public_ip = value;
    doorForm.public_ip = value;
});

function requestPunch(punchState: 0 | 1) {
    if (!selectedPunchSiteId.value || !canPunch.value) {
        return;
    }

    pendingPunchState.value = punchState;
    confirmOpen.value = true;
}

function closeConfirm() {
    if (punchForm.processing) {
        return;
    }

    confirmOpen.value = false;
    pendingPunchState.value = null;
}

function confirmPunch() {
    if (pendingPunchState.value === null || !selectedPunchSiteId.value || !coords.value || !canPunch.value || !deviceId.value) {
        return;
    }

    punchForm.self_punch_site_id = selectedPunchSiteId.value;
    punchForm.punch_state = pendingPunchState.value;
    punchForm.latitude = coords.value.latitude;
    punchForm.longitude = coords.value.longitude;
    punchForm.accuracy_meters = coords.value.accuracy;
    punchForm.public_ip = publicIp.value;
    punchForm.device_id = deviceId.value;

    punchForm.post('/self-punch', {
        preserveScroll: true,
        onSuccess: () => {
            confirmOpen.value = false;
            pendingPunchState.value = null;
        },
    });
}

function ensureDoorCooldownTimer() {
    if (doorCooldownTimer !== null) {
        return;
    }

    doorCooldownTimer = setInterval(() => {
        if (doorCooldownRemaining.value <= 1) {
            doorCooldownRemaining.value = 0;
            stopDoorCooldown();
            return;
        }

        doorCooldownRemaining.value -= 1;
    }, 1000);
}

function openDoor() {
    if (!selectedDoorSiteId.value || !coords.value || !canOpenDoor.value) {
        return;
    }

    doorForm.remote_door_site_id = selectedDoorSiteId.value;
    doorForm.latitude = coords.value.latitude;
    doorForm.longitude = coords.value.longitude;
    doorForm.accuracy_meters = coords.value.accuracy;
    doorForm.public_ip = publicIp.value;

    doorForm.post('/self-punch/open-door', {
        preserveScroll: true,
        onSuccess: () => {
            doorCooldownRemaining.value = props.doorOpenCooldownSeconds;
            ensureDoorCooldownTimer();
        },
    });
}

onMounted(() => {
    geofence.refreshLocation();
    void geofence.refreshPublicIp();

    if (doorCooldownRemaining.value > 0) {
        ensureDoorCooldownTimer();
    }
});

onUnmounted(() => {
    stopDoorCooldown();
});

function stopDoorCooldown() {
    if (doorCooldownTimer === null) {
        return;
    }

    clearInterval(doorCooldownTimer);
    doorCooldownTimer = null;
}
</script>

<template>
    <Head title="Mobile Punch" />

    <AppLayout>
        <div class="self-punch mx-auto max-w-lg pb-8">
            <header class="self-punch-fade mb-6 text-center">
                <p class="text-sm font-medium text-brand-600 dark:text-brand-400">Mobile punch</p>
                <p v-if="employee" class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                    Hello, {{ employee.name.split(' ')[0] }}
                </p>
                <div class="mt-5 inline-block">
                    <p class="self-punch-clock font-mono text-5xl font-bold tabular-nums tracking-tight text-slate-900 dark:text-white sm:text-6xl">
                        {{ time }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ date }} · {{ timezoneLabel }}
                    </p>
                </div>
            </header>

            <div
                v-if="!employee"
                class="self-punch-fade self-punch-card rounded-3xl border border-slate-200/80 bg-white p-6 text-center shadow-sm dark:border-slate-800 dark:bg-surface-elevated"
            >
                <p class="font-medium text-slate-900 dark:text-white">Profile not linked</p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Ask HR to link your login to an employee record.</p>
            </div>

            <template v-else>
                <div v-if="showTabs" class="self-punch-fade mb-6 flex rounded-2xl bg-slate-100 p-1 dark:border dark:border-slate-800 dark:bg-surface-muted">
                    <button
                        type="button"
                        class="flex-1 rounded-xl py-2.5 text-sm font-semibold transition"
                        :class="activeTab === 'punch' ? 'bg-white text-slate-900 shadow-sm dark:bg-surface-elevated dark:text-white dark:shadow-black/20' : 'text-slate-500 dark:text-slate-400'"
                        @click="activeTab = 'punch'"
                    >
                        Punch
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl py-2.5 text-sm font-semibold transition"
                        :class="activeTab === 'door' ? 'bg-white text-slate-900 shadow-sm dark:bg-surface-elevated dark:text-white dark:shadow-black/20' : 'text-slate-500 dark:text-slate-400'"
                        @click="activeTab = 'door'"
                    >
                        Open door
                    </button>
                </div>

                <!-- Punch tab -->
                <template v-if="activeTab === 'punch'">
                    <div
                        v-if="sites.length === 0"
                        class="self-punch-fade rounded-3xl border border-slate-200/80 bg-white p-6 text-center shadow-sm dark:border-slate-800 dark:bg-surface-elevated"
                    >
                        <p class="font-medium text-slate-900 dark:text-white">No punch sites assigned</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Ask an administrator to assign you to a mobile punch site.</p>
                    </div>

                    <template v-else>
                        <div v-if="sites.length > 1" class="self-punch-fade mb-4 flex flex-wrap justify-center gap-2">
                            <button
                                v-for="site in sites"
                                :key="site.id"
                                type="button"
                                class="rounded-full px-4 py-2 text-sm font-medium transition-all"
                                :class="selectedPunchSiteId === site.id ? 'bg-brand-600 text-white shadow-md dark:shadow-brand-900/40' : 'bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-surface-elevated dark:text-slate-300 dark:ring-slate-700'"
                                @click="selectedPunchSiteId = site.id"
                            >
                                {{ site.name }}
                            </button>
                        </div>

                        <GeofenceStatusCard
                            class="self-punch-fade mb-6"
                            :selected-site="selectedPunchSite"
                            :gps-ready="gpsReady"
                            :wifi-ready="wifiReady"
                            :locating="locating"
                            :public-ip-checking="publicIpChecking"
                            :network-blocked="networkBlocked"
                            :coords="coords"
                            ready-label="You're ready to punch"
                            :not-ready-messages="{ gps: 'Allow location access to punch' }"
                        />

                        <div class="self-punch-fade mb-6 grid grid-cols-2 gap-4">
                            <button
                                type="button"
                                class="punch-btn group relative flex flex-col items-center gap-3 rounded-3xl border-2 p-6 transition-all"
                                :class="
                                    canPunch
                                        ? 'border-emerald-200 bg-gradient-to-b from-emerald-50 to-white active:scale-[0.98] dark:border-emerald-800/60 dark:from-emerald-950/45 dark:to-surface-elevated'
                                        : 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-70 dark:border-slate-700 dark:bg-surface-muted/50'
                                "
                                :disabled="!canPunch"
                                @click="requestPunch(0)"
                            >
                                <span v-if="canPunch && suggestedAction === 0" class="absolute right-3 top-3 rounded-full bg-emerald-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white">Suggested</span>
                                <span
                                    class="flex h-16 w-16 items-center justify-center rounded-2xl"
                                    :class="canPunch ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-400 dark:bg-slate-700 dark:text-slate-500'"
                                >
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" /></svg>
                                </span>
                                <span class="text-base font-semibold text-slate-900 dark:text-white">Check in</span>
                            </button>
                            <button
                                type="button"
                                class="punch-btn group relative flex flex-col items-center gap-3 rounded-3xl border-2 p-6 transition-all"
                                :class="
                                    canPunch
                                        ? 'border-slate-200 bg-gradient-to-b from-slate-50 to-white active:scale-[0.98] dark:border-slate-700 dark:from-slate-800/60 dark:to-surface-elevated'
                                        : 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-70 dark:border-slate-700 dark:bg-surface-muted/50'
                                "
                                :disabled="!canPunch"
                                @click="requestPunch(1)"
                            >
                                <span v-if="canPunch && suggestedAction === 1" class="absolute right-3 top-3 rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold uppercase text-white">Suggested</span>
                                <span
                                    class="flex h-16 w-16 items-center justify-center rounded-2xl"
                                    :class="canPunch ? 'bg-slate-700 text-white dark:bg-slate-600' : 'bg-slate-200 text-slate-400 dark:bg-slate-700 dark:text-slate-500'"
                                >
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                </span>
                                <span class="text-base font-semibold text-slate-900 dark:text-white">Check out</span>
                            </button>
                        </div>

                        <div v-if="Object.keys(punchForm.errors).length" class="mb-4 space-y-1 text-center text-sm text-red-600 dark:text-red-400">
                            <p v-for="(err, key) in punchForm.errors" :key="key">{{ err }}</p>
                        </div>

                        <div class="self-punch-fade mb-4">
                            <h2 class="mb-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Today's punches</h2>
                            <div v-if="todaysPunches.length" class="rounded-3xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-surface-elevated">
                                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <li v-for="row in todaysPunches" :key="row.id" class="flex justify-between py-2 text-sm first:pt-0 last:pb-0">
                                        <span class="text-slate-900 dark:text-slate-100">{{ row.punch_state_label }} · {{ row.site_name }}</span>
                                        <span class="font-mono tabular-nums text-slate-500 dark:text-slate-400">{{ row.punched_at }}</span>
                                    </li>
                                </ul>
                            </div>
                            <p v-else class="text-center text-sm text-slate-400 dark:text-slate-500">No punches today</p>
                        </div>
                    </template>
                </template>

                <!-- Door tab -->
                <template v-else>
                    <div
                        v-if="doorSites.length === 0"
                        class="self-punch-fade rounded-3xl border border-slate-200/80 bg-white p-6 text-center shadow-sm dark:border-slate-800 dark:bg-surface-elevated"
                    >
                        <p class="font-medium text-slate-900 dark:text-white">No door sites assigned</p>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Ask an administrator to assign you to a remote door site.</p>
                    </div>

                    <template v-else>
                        <div v-if="doorSites.length > 1" class="self-punch-fade mb-4 flex flex-wrap justify-center gap-2">
                            <button
                                v-for="site in doorSites"
                                :key="site.id"
                                type="button"
                                class="rounded-full px-4 py-2 text-sm font-medium transition-all"
                                :class="selectedDoorSiteId === site.id ? 'bg-brand-600 text-white shadow-md dark:shadow-brand-900/40' : 'bg-white text-slate-600 ring-1 ring-slate-200 dark:bg-surface-elevated dark:text-slate-300 dark:ring-slate-700'"
                                @click="selectedDoorSiteId = site.id"
                            >
                                {{ site.name }}
                            </button>
                        </div>

                        <p v-else-if="selectedDoorSite" class="self-punch-fade mb-4 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ selectedDoorSite.name }} · {{ selectedDoorSite.device.name }}
                        </p>

                        <GeofenceStatusCard
                            class="self-punch-fade mb-6"
                            :selected-site="selectedDoorSite"
                            :gps-ready="gpsReady"
                            :wifi-ready="wifiReady"
                            :locating="locating"
                            :public-ip-checking="publicIpChecking"
                            :network-blocked="networkBlocked"
                            :coords="coords"
                            ready-label="You're ready to open the door"
                            :not-ready-messages="{ gps: 'Allow location access to open the door' }"
                        />

                        <button
                            type="button"
                            class="door-btn self-punch-fade mb-6 flex w-full flex-col items-center gap-4 rounded-3xl border-2 p-8 transition-all"
                            :class="
                                canOpenDoor
                                    ? 'border-brand-300 bg-gradient-to-b from-brand-50 to-white hover:shadow-lg hover:shadow-brand-500/15 active:scale-[0.98] dark:border-brand-700/60 dark:from-brand-950/35 dark:to-surface-elevated dark:hover:shadow-brand-500/10'
                                    : 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-70 dark:border-slate-700 dark:bg-surface-muted/50'
                            "
                            :disabled="!canOpenDoor"
                            @click="openDoor"
                        >
                            <span
                                class="flex h-24 w-24 items-center justify-center rounded-full transition-transform"
                                :class="canOpenDoor ? 'bg-brand-500 text-white shadow-xl shadow-brand-500/30 door-pulse dark:shadow-brand-900/50' : 'bg-slate-200 text-slate-400 dark:bg-slate-700 dark:text-slate-500'"
                            >
                                <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <span class="text-lg font-bold text-slate-900 dark:text-white">{{ doorButtonLabel }}</span>
                            <span v-if="selectedDoorSite" class="text-xs text-slate-500 dark:text-slate-400">{{ selectedDoorSite.device.name }}</span>
                        </button>

                        <div v-if="Object.keys(doorForm.errors).length" class="mb-4 space-y-1 text-center text-sm text-red-600 dark:text-red-400">
                            <p v-for="(err, key) in doorForm.errors" :key="key">{{ err }}</p>
                        </div>

                        <p v-if="publicIpError" class="mb-4 text-center text-sm text-red-600 dark:text-red-400">{{ publicIpError }}</p>

                        <div class="self-punch-fade mb-4">
                            <h2 class="mb-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Today's opens</h2>
                            <div v-if="todaysDoorOpens.length" class="rounded-3xl border border-slate-200/80 bg-white p-4 dark:border-slate-800 dark:bg-surface-elevated">
                                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <li v-for="row in todaysDoorOpens" :key="row.id" class="flex justify-between py-2 text-sm first:pt-0 last:pb-0">
                                        <span class="text-slate-900 dark:text-slate-100">{{ row.site_name }} · {{ row.device_name }}</span>
                                        <span class="font-mono tabular-nums text-slate-500 dark:text-slate-400">{{ row.opened_at }}</span>
                                    </li>
                                </ul>
                            </div>
                            <p v-else class="text-center text-sm text-slate-400 dark:text-slate-500">No door opens today</p>
                        </div>
                    </template>
                </template>

                <details class="self-punch-fade mt-2 text-center">
                    <summary class="cursor-pointer text-xs text-slate-400 dark:text-slate-500">Having trouble?</summary>
                    <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-left text-xs text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <p v-if="publicIp">Public IP: {{ publicIp }}</p>
                        <p v-if="coords">GPS accuracy: ±{{ Math.round(coords.accuracy ?? 0) }}m</p>
                        <button
                            type="button"
                            class="mt-3 w-full rounded-xl bg-slate-100 py-2 text-sm font-medium text-slate-700 dark:bg-surface-muted dark:text-slate-200"
                            @click="refreshAll()"
                        >
                            Refresh location & network
                        </button>
                    </div>
                </details>
            </template>
        </div>

        <UiModal :open="confirmOpen" title="" max-width="sm" @close="closeConfirm">
            <div class="text-center">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ pendingPunchLabel }}?</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">at {{ selectedPunchSite?.name }}</p>
                <p class="mt-1 font-mono text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ time }}</p>
            </div>
            <template #footer>
                <div class="flex w-full gap-3">
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-slate-200 py-3 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-200"
                        :disabled="punchForm.processing"
                        @click="closeConfirm"
                    >
                        Cancel
                    </button>
                    <button type="button" class="flex-1 rounded-xl bg-emerald-600 py-3 text-sm font-semibold text-white" :disabled="punchForm.processing" @click="confirmPunch">
                        {{ punchForm.processing ? 'Saving…' : 'Confirm' }}
                    </button>
                </div>
            </template>
        </UiModal>
    </AppLayout>
</template>

<style scoped>
.self-punch-fade {
    animation: self-punch-fade-in 0.5s ease-out both;
}

@keyframes self-punch-fade-in {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.door-pulse {
    animation: door-pulse 2s ease-in-out infinite;
}

@keyframes door-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgb(245 158 11 / 0.4); }
    50% { box-shadow: 0 0 0 16px rgb(245 158 11 / 0); }
}

.confirm-icon {
    animation: confirm-pop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@keyframes confirm-pop {
    from { opacity: 0; transform: scale(0.6); }
    to { opacity: 1; transform: scale(1); }
}
</style>
