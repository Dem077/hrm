<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

import PageHeader from '@/components/ui/PageHeader.vue';
import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiSelect from '@/components/ui/UiSelect.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/format';
import type { Paginated } from '@/types/zkt';

type SiteOption = {
    id: number;
    name: string;
    code: string | null;
};

type EmployeeRef = {
    id: number;
    name: string;
    staff_id: string;
};

type SiteRef = {
    id: number;
    name: string;
    code: string | null;
};

type PunchAccessLog = {
    id: number;
    event_type: 'punch';
    occurred_at: string | null;
    punch_state_label: string;
    employee: EmployeeRef | null;
    device_user_id: string;
    site: SiteRef | null;
    client_ip: string | null;
    request_ip: string | null;
    client_device_id: string | null;
    latitude: number | null;
    longitude: number | null;
    accuracy_meters: number | null;
    created_at: string | null;
};

type DoorAccessLog = {
    id: number;
    event_type: 'door_open';
    occurred_at: string | null;
    employee: EmployeeRef | null;
    user: { id: number; name: string; email: string } | null;
    site: SiteRef | null;
    device: { id: number; name: string } | null;
    status: string;
    result_message: string | null;
    client_ip: string | null;
    latitude: number | null;
    longitude: number | null;
    accuracy_meters: number | null;
    zkt_adms_command_id: number | null;
    created_at: string | null;
};

const props = defineProps<{
    activeTab: 'punches' | 'doors';
    punchLogs: Paginated<PunchAccessLog>;
    doorLogs: Paginated<DoorAccessLog>;
    punchSites: SiteOption[];
    doorSites: SiteOption[];
    filters: {
        search?: string | null;
        site_id?: string | number | null;
        client_device_id?: string | null;
        date_from?: string | null;
        date_to?: string | null;
    };
}>();

const filters = reactive({
    search: props.filters.search ?? '',
    site_id: props.filters.site_id ?? '',
    client_device_id: props.filters.client_device_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const expandedPunchId = ref<number | null>(null);
const expandedDoorId = ref<number | null>(null);

const siteOptions = computed(() => (props.activeTab === 'doors' ? props.doorSites : props.punchSites));

function applyFilters() {
    router.get(
        '/mobile-punch-logs',
        {
            tab: props.activeTab,
            ...filters,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function switchTab(tab: 'punches' | 'doors') {
    expandedPunchId.value = null;
    expandedDoorId.value = null;

    router.get(
        '/mobile-punch-logs',
        {
            tab,
            ...filters,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function togglePunchDetails(id: number) {
    expandedPunchId.value = expandedPunchId.value === id ? null : id;
}

function toggleDoorDetails(id: number) {
    expandedDoorId.value = expandedDoorId.value === id ? null : id;
}

function siteLabel(site: SiteRef | null): string {
    if (!site) {
        return '—';
    }

    return site.code ? `${site.name} (${site.code})` : site.name;
}

function doorStatusColor(status: string): string {
    return status === 'opened' ? 'success' : status === 'queued' ? 'warning' : 'gray';
}
</script>

<template>
    <Head title="Mobile Punch Logs" />

    <AppLayout>
        <PageHeader
            title="Mobile punch logs"
            description="Audit trail for mobile punch check-ins and remote door opens, including device codes, IPs, and GPS."
        />

        <div class="mb-6 flex w-full max-w-md rounded-2xl bg-slate-100 p-1 dark:border dark:border-slate-800 dark:bg-surface-muted">
            <button
                type="button"
                class="flex-1 rounded-xl py-2.5 text-sm font-semibold transition"
                :class="activeTab === 'punches' ? 'bg-white text-slate-900 shadow-sm dark:bg-surface-elevated dark:text-white dark:shadow-black/20' : 'text-slate-500 dark:text-slate-400'"
                @click="switchTab('punches')"
            >
                Punches
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl py-2.5 text-sm font-semibold transition"
                :class="activeTab === 'doors' ? 'bg-white text-slate-900 shadow-sm dark:bg-surface-elevated dark:text-white dark:shadow-black/20' : 'text-slate-500 dark:text-slate-400'"
                @click="switchTab('doors')"
            >
                Door opens
            </button>
        </div>

        <div class="mb-6">
            <UiCard padding="sm">
                <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" @submit.prevent="applyFilters">
                    <UiInput v-model="filters.search" label="Search" placeholder="Employee, site, IP, device code" />
                    <UiSelect v-model="filters.site_id" label="Site">
                        <option value="">All sites</option>
                        <option v-for="site in siteOptions" :key="site.id" :value="site.id">
                            {{ site.code ? `${site.name} (${site.code})` : site.name }}
                        </option>
                    </UiSelect>
                    <UiInput
                        v-if="activeTab === 'punches'"
                        v-model="filters.client_device_id"
                        label="Device code"
                        placeholder="Browser device UUID"
                    />
                    <UiInput v-model="filters.date_from" label="From date" type="date" />
                    <UiInput v-model="filters.date_to" label="To date" type="date" />
                    <div class="flex items-end md:col-span-2 xl:col-span-1">
                        <UiButton type="submit" variant="primary">Apply filters</UiButton>
                    </div>
                </form>
            </UiCard>
        </div>

        <UiCard v-if="activeTab === 'punches'" padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Time</th>
                            <th class="px-5 py-3.5 font-medium">Employee</th>
                            <th class="px-5 py-3.5 font-medium">Action</th>
                            <th class="px-5 py-3.5 font-medium">Site</th>
                            <th class="px-5 py-3.5 font-medium">Public IP</th>
                            <th class="px-5 py-3.5 font-medium">Device code</th>
                            <th class="px-5 py-3.5 font-medium" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template v-for="log in punchLogs.data" :key="log.id">
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-surface-elevated/60">
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(log.occurred_at) }}</td>
                                <td class="px-5 py-4">
                                    <Link
                                        v-if="log.employee"
                                        :href="`/employees/${log.employee.id}`"
                                        class="font-medium text-brand-700 hover:text-brand-600 hover:underline dark:text-brand-400"
                                    >
                                        {{ log.employee.name }}
                                    </Link>
                                    <span v-else class="text-slate-500">{{ log.device_user_id }}</span>
                                    <p v-if="log.employee" class="font-mono text-xs text-slate-500">{{ log.employee.staff_id }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ log.punch_state_label }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ siteLabel(log.site) }}</td>
                                <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ log.client_ip ?? '—' }}</td>
                                <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ log.client_device_id ?? '—' }}</td>
                                <td class="px-5 py-4 text-right">
                                    <button
                                        type="button"
                                        class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"
                                        @click="togglePunchDetails(log.id)"
                                    >
                                        {{ expandedPunchId === log.id ? 'Hide' : 'Details' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="expandedPunchId === log.id">
                                <td colspan="7" class="bg-slate-50 px-5 py-4 dark:bg-surface-muted/40">
                                    <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Log ID</dt>
                                            <dd class="mt-1 font-mono text-slate-700 dark:text-slate-200">#{{ log.id }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Request IP</dt>
                                            <dd class="mt-1 font-mono text-slate-700 dark:text-slate-200">{{ log.request_ip ?? '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Public IP</dt>
                                            <dd class="mt-1 font-mono text-slate-700 dark:text-slate-200">{{ log.client_ip ?? '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Device code</dt>
                                            <dd class="mt-1 break-all font-mono text-slate-700 dark:text-slate-200">{{ log.client_device_id ?? '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">GPS</dt>
                                            <dd class="mt-1 font-mono text-slate-700 dark:text-slate-200">
                                                <template v-if="log.latitude !== null && log.longitude !== null">
                                                    {{ log.latitude }}, {{ log.longitude }}
                                                    <span v-if="log.accuracy_meters !== null"> (±{{ log.accuracy_meters }}m)</span>
                                                </template>
                                                <template v-else>—</template>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Recorded at</dt>
                                            <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ formatDateTime(log.created_at) }}</dd>
                                        </div>
                                    </dl>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="punchLogs.data.length === 0">
                            <td colspan="7" class="px-5 py-12 text-center text-slate-500">No mobile punch logs found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <UiCard v-else padding="none">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3.5 font-medium">Time</th>
                            <th class="px-5 py-3.5 font-medium">Employee</th>
                            <th class="px-5 py-3.5 font-medium">Site</th>
                            <th class="px-5 py-3.5 font-medium">Machine</th>
                            <th class="px-5 py-3.5 font-medium">Status</th>
                            <th class="px-5 py-3.5 font-medium">IP</th>
                            <th class="px-5 py-3.5 font-medium" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template v-for="log in doorLogs.data" :key="log.id">
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-surface-elevated/60">
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ formatDateTime(log.occurred_at) }}</td>
                                <td class="px-5 py-4">
                                    <Link
                                        v-if="log.employee"
                                        :href="`/employees/${log.employee.id}`"
                                        class="font-medium text-brand-700 hover:text-brand-600 hover:underline dark:text-brand-400"
                                    >
                                        {{ log.employee.name }}
                                    </Link>
                                    <span v-else class="text-slate-500">—</span>
                                    <p v-if="log.employee" class="font-mono text-xs text-slate-500">{{ log.employee.staff_id }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ siteLabel(log.site) }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ log.device?.name ?? '—' }}</td>
                                <td class="px-5 py-4">
                                    <UiBadge :label="log.status" :color="doorStatusColor(log.status)" />
                                </td>
                                <td class="px-5 py-4 font-mono text-xs text-slate-600 dark:text-slate-400">{{ log.client_ip ?? '—' }}</td>
                                <td class="px-5 py-4 text-right">
                                    <button
                                        type="button"
                                        class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"
                                        @click="toggleDoorDetails(log.id)"
                                    >
                                        {{ expandedDoorId === log.id ? 'Hide' : 'Details' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="expandedDoorId === log.id">
                                <td colspan="7" class="bg-slate-50 px-5 py-4 dark:bg-surface-muted/40">
                                    <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Log ID</dt>
                                            <dd class="mt-1 font-mono text-slate-700 dark:text-slate-200">#{{ log.id }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Login user</dt>
                                            <dd class="mt-1 text-slate-700 dark:text-slate-200">
                                                {{ log.user?.name ?? '—' }}
                                                <span v-if="log.user?.email" class="block text-xs text-slate-500">{{ log.user.email }}</span>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Result</dt>
                                            <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ log.result_message ?? '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">ADMS command</dt>
                                            <dd class="mt-1 font-mono text-slate-700 dark:text-slate-200">{{ log.zkt_adms_command_id ?? '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">GPS</dt>
                                            <dd class="mt-1 font-mono text-slate-700 dark:text-slate-200">
                                                <template v-if="log.latitude !== null && log.longitude !== null">
                                                    {{ log.latitude }}, {{ log.longitude }}
                                                    <span v-if="log.accuracy_meters !== null"> (±{{ log.accuracy_meters }}m)</span>
                                                </template>
                                                <template v-else>—</template>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Recorded at</dt>
                                            <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ formatDateTime(log.created_at) }}</dd>
                                        </div>
                                    </dl>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="doorLogs.data.length === 0">
                            <td colspan="7" class="px-5 py-12 text-center text-slate-500">No door open logs found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>

        <div
            v-if="(activeTab === 'punches' ? punchLogs.links : doorLogs.links).length > 3"
            class="mt-5 flex flex-wrap gap-2"
        >
            <Link
                v-for="link in activeTab === 'punches' ? punchLogs.links : doorLogs.links"
                :key="`${link.label}-${link.url}`"
                :href="link.url ?? '#'"
                class="rounded-lg border px-3 py-1.5 text-sm transition"
                :class="[
                    link.active
                        ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-600/40 dark:bg-brand-600/15 dark:text-brand-400'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-400 dark:hover:bg-surface-muted dark:hover:text-slate-200',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                v-html="link.label"
            />
        </div>
    </AppLayout>
</template>
