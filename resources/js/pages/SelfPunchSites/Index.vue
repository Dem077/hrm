<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import GeoFenceMapPicker from '@/components/GeoFenceMapPicker.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';

type SelfPunchSite = {
    id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    latitude: number | null;
    longitude: number | null;
    radius_meters: number;
    max_accuracy_meters: number;
    allowed_public_ips: string[];
    allowed_public_ips_text?: string;
    require_public_ip: boolean;
    sort_order: number;
    is_active: boolean;
    employees_count?: number;
    employee_ids: number[];
    employees?: Array<{ id: number; name: string; staff_id: string }>;
};

type RemoteDoorSite = SelfPunchSite & {
    zkt_device_id: number | null;
    device_name?: string | null;
};

const props = defineProps<{
    sites: SelfPunchSite[];
    doorSites: RemoteDoorSite[];
    accessDevices: Array<{ id: number; name: string; connection_mode: string; serial_number: string | null }>;
    employees: Array<{ id: number; name: string; staff_id: string }>;
    emptySite: SelfPunchSite;
    emptyDoorSite: RemoteDoorSite;
}>();

const { can } = usePermissions();
const activeTab = ref<'punch' | 'door'>('punch');
const editingId = ref<number | null>(null);
const editingDoorId = ref<number | null>(null);

const form = useForm({
    name: props.emptySite.name,
    code: props.emptySite.code ?? '',
    description: props.emptySite.description ?? '',
    latitude: props.emptySite.latitude as number | string | null,
    longitude: props.emptySite.longitude as number | string | null,
    radius_meters: props.emptySite.radius_meters,
    max_accuracy_meters: props.emptySite.max_accuracy_meters,
    require_public_ip: props.emptySite.require_public_ip,
    allowed_public_ips_text: props.emptySite.allowed_public_ips_text ?? '',
    sort_order: props.emptySite.sort_order,
    is_active: props.emptySite.is_active,
    employee_ids: [...(props.emptySite.employee_ids ?? [])],
});

const doorForm = useForm({
    zkt_device_id: props.emptyDoorSite.zkt_device_id as number | string | null,
    name: props.emptyDoorSite.name,
    code: props.emptyDoorSite.code ?? '',
    description: props.emptyDoorSite.description ?? '',
    latitude: props.emptyDoorSite.latitude as number | string | null,
    longitude: props.emptyDoorSite.longitude as number | string | null,
    radius_meters: props.emptyDoorSite.radius_meters,
    max_accuracy_meters: props.emptyDoorSite.max_accuracy_meters,
    require_public_ip: props.emptyDoorSite.require_public_ip,
    allowed_public_ips_text: props.emptyDoorSite.allowed_public_ips_text ?? '',
    sort_order: props.emptyDoorSite.sort_order,
    is_active: props.emptyDoorSite.is_active,
    employee_ids: [...(props.emptyDoorSite.employee_ids ?? [])],
});

function resetForm() {
    editingId.value = null;
    form.reset();
    form.name = props.emptySite.name;
    form.code = props.emptySite.code ?? '';
    form.description = props.emptySite.description ?? '';
    form.latitude = props.emptySite.latitude;
    form.longitude = props.emptySite.longitude;
    form.radius_meters = props.emptySite.radius_meters;
    form.max_accuracy_meters = props.emptySite.max_accuracy_meters;
    form.require_public_ip = props.emptySite.require_public_ip;
    form.allowed_public_ips_text = props.emptySite.allowed_public_ips_text ?? '';
    form.sort_order = props.emptySite.sort_order;
    form.is_active = props.emptySite.is_active;
    form.employee_ids = [...(props.emptySite.employee_ids ?? [])];
    form.clearErrors();
}

function editSite(site: SelfPunchSite) {
    editingId.value = site.id;
    form.name = site.name;
    form.code = site.code ?? '';
    form.description = site.description ?? '';
    form.latitude = site.latitude;
    form.longitude = site.longitude;
    form.radius_meters = site.radius_meters;
    form.max_accuracy_meters = site.max_accuracy_meters;
    form.require_public_ip = site.require_public_ip;
    form.allowed_public_ips_text = site.allowed_public_ips_text ?? site.allowed_public_ips.join('\n');
    form.sort_order = site.sort_order;
    form.is_active = site.is_active;
    form.employee_ids = [...(site.employee_ids ?? site.employees?.map((e) => e.id) ?? [])];
    form.clearErrors();
}

function toggleEmployee(employeeId: number) {
    const index = form.employee_ids.indexOf(employeeId);
    if (index >= 0) {
        form.employee_ids.splice(index, 1);
        return;
    }
    form.employee_ids.push(employeeId);
}

function toggleDoorEmployee(employeeId: number) {
    const index = doorForm.employee_ids.indexOf(employeeId);
    if (index >= 0) {
        doorForm.employee_ids.splice(index, 1);
        return;
    }
    doorForm.employee_ids.push(employeeId);
}

function submit() {
    if (editingId.value) {
        form.put(`/self-punch-sites/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: resetForm,
        });
        return;
    }

    form.post('/self-punch-sites', {
        preserveScroll: true,
        onSuccess: resetForm,
    });
}

function destroySite(id: number, name: string) {
    if (confirm(`Delete mobile punch site "${name}"? Assigned employees will lose access.`)) {
        router.delete(`/self-punch-sites/${id}`, { preserveScroll: true });
    }
}

function resetDoorForm() {
    editingDoorId.value = null;
    doorForm.reset();
    doorForm.zkt_device_id = props.emptyDoorSite.zkt_device_id;
    doorForm.name = props.emptyDoorSite.name;
    doorForm.code = props.emptyDoorSite.code ?? '';
    doorForm.description = props.emptyDoorSite.description ?? '';
    doorForm.latitude = props.emptyDoorSite.latitude;
    doorForm.longitude = props.emptyDoorSite.longitude;
    doorForm.radius_meters = props.emptyDoorSite.radius_meters;
    doorForm.max_accuracy_meters = props.emptyDoorSite.max_accuracy_meters;
    doorForm.require_public_ip = props.emptyDoorSite.require_public_ip;
    doorForm.allowed_public_ips_text = props.emptyDoorSite.allowed_public_ips_text ?? '';
    doorForm.sort_order = props.emptyDoorSite.sort_order;
    doorForm.is_active = props.emptyDoorSite.is_active;
    doorForm.employee_ids = [...(props.emptyDoorSite.employee_ids ?? [])];
    doorForm.clearErrors();
}

function editDoorSite(site: RemoteDoorSite) {
    editingDoorId.value = site.id;
    doorForm.zkt_device_id = site.zkt_device_id;
    doorForm.name = site.name;
    doorForm.code = site.code ?? '';
    doorForm.description = site.description ?? '';
    doorForm.latitude = site.latitude;
    doorForm.longitude = site.longitude;
    doorForm.radius_meters = site.radius_meters;
    doorForm.max_accuracy_meters = site.max_accuracy_meters;
    doorForm.require_public_ip = site.require_public_ip;
    doorForm.allowed_public_ips_text = site.allowed_public_ips_text ?? site.allowed_public_ips.join('\n');
    doorForm.sort_order = site.sort_order;
    doorForm.is_active = site.is_active;
    doorForm.employee_ids = [...(site.employee_ids ?? site.employees?.map((e) => e.id) ?? [])];
    doorForm.clearErrors();
}

function submitDoor() {
    if (editingDoorId.value) {
        doorForm.put(`/remote-door-sites/${editingDoorId.value}`, {
            preserveScroll: true,
            onSuccess: resetDoorForm,
        });
        return;
    }

    doorForm.post('/remote-door-sites', {
        preserveScroll: true,
        onSuccess: resetDoorForm,
    });
}

function destroyDoorSite(id: number, name: string) {
    if (confirm(`Delete remote door site "${name}"? Assigned employees will lose access.`)) {
        router.delete(`/remote-door-sites/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Mobile Punch Sites" />

    <AppLayout>
        <PageHeader
            title="Remote access sites"
            description="Configure geofenced mobile punch locations and remote door sites linked to access machines."
        />

        <div class="mb-6 flex w-full max-w-md rounded-2xl bg-slate-100 p-1 dark:bg-surface-muted">
            <button
                type="button"
                class="flex-1 rounded-xl py-2.5 text-sm font-semibold transition"
                :class="activeTab === 'punch' ? 'bg-white text-slate-900 shadow-sm dark:bg-surface-elevated dark:text-white' : 'text-slate-500'"
                @click="activeTab = 'punch'"
            >
                Mobile punch
            </button>
            <button
                type="button"
                class="flex-1 rounded-xl py-2.5 text-sm font-semibold transition"
                :class="activeTab === 'door' ? 'bg-white text-slate-900 shadow-sm dark:bg-surface-elevated dark:text-white' : 'text-slate-500'"
                @click="activeTab = 'door'"
            >
                Remote door
            </button>
        </div>

        <div v-if="activeTab === 'punch'" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
            <UiCard padding="none">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Site</th>
                                <th class="px-5 py-3.5 font-medium">Radius</th>
                                <th class="px-5 py-3.5 font-medium">Network</th>
                                <th class="px-5 py-3.5 font-medium">Employees</th>
                                <th class="px-5 py-3.5 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="site in sites" :key="site.id!">
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ site.name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ site.latitude }}, {{ site.longitude }}
                                        <span v-if="!site.is_active"> · Inactive</span>
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ site.radius_meters }}m</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ site.require_public_ip ? `${site.allowed_public_ips.length} IP(s)` : 'Any' }}
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">
                                    {{ site.employees_count ?? site.employees?.length ?? 0 }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <UiButton
                                            v-if="can('self-punch-sites.update')"
                                            size="sm"
                                            variant="ghost"
                                            @click="editSite(site)"
                                        >
                                            Edit
                                        </UiButton>
                                        <UiButton
                                            v-if="can('self-punch-sites.delete')"
                                            size="sm"
                                            variant="danger"
                                            @click="destroySite(site.id!, site.name)"
                                        >
                                            Delete
                                        </UiButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="sites.length === 0">
                                <td colspan="5" class="px-5 py-8 text-center text-slate-500">
                                    No mobile punch sites yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>

            <UiCard
                v-if="can('self-punch-sites.create') || can('self-punch-sites.update')"
                :title="editingId ? 'Edit site' : 'Add site'"
                description="Place the pin on the map, set the radius circle, then assign employees."
            >
                <form class="space-y-5" @submit.prevent="submit">
                    <UiInput v-model="form.name" label="Name" required :error="form.errors.name" />
                    <UiInput v-model="form.code" label="Code" hint="Optional short code" :error="form.errors.code" />
                    <UiInput v-model="form.description" label="Description" :error="form.errors.description" />

                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Location on map</p>
                        <GeoFenceMapPicker
                            :key="editingId ?? 'new'"
                            v-model:latitude="form.latitude"
                            v-model:longitude="form.longitude"
                            v-model:radius-meters="form.radius_meters"
                            :error="form.errors.latitude || form.errors.longitude || form.errors.radius_meters"
                        />
                    </div>

                    <UiInput
                        v-model="form.max_accuracy_meters"
                        label="Max GPS accuracy (m)"
                        type="number"
                        min="10"
                        required
                        hint="Allow weaker GPS readings up to this accuracy (phones often report ±100–150m)"
                        :error="form.errors.max_accuracy_meters"
                    />

                    <UiInput v-model="form.sort_order" label="Sort order" type="number" min="0" :error="form.errors.sort_order" />

                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                        Site is active
                    </label>

                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
                        <input v-model="form.require_public_ip" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                        Require allowed public IP / CIDR
                    </label>

                    <div v-if="form.require_public_ip">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Allowed public IPs / CIDRs
                        </label>
                        <textarea
                            v-model="form.allowed_public_ips_text"
                            rows="4"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 dark:border-slate-700 dark:bg-surface-elevated"
                            placeholder="203.0.113.10&#10;203.0.113.0/24"
                        />
                        <p class="mt-1.5 text-xs text-slate-500">One IP or CIDR per line.</p>
                        <p v-if="form.errors.allowed_public_ips_text || form.errors.allowed_public_ips" class="mt-1 text-sm text-red-600">
                            {{ form.errors.allowed_public_ips_text || (form.errors as Record<string, string>).allowed_public_ips }}
                        </p>
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Allowed employees</p>
                        <p class="mb-2 text-xs text-slate-500">Only employees with a login account are listed.</p>
                        <div class="max-h-56 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <label
                                v-for="employee in employees"
                                :key="employee.id"
                                class="flex items-start gap-3 rounded-lg px-2 py-2 text-sm hover:bg-slate-50 dark:hover:bg-surface-muted"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-brand-600"
                                    :checked="form.employee_ids.includes(employee.id)"
                                    @change="toggleEmployee(employee.id)"
                                />
                                <span>
                                    <span class="font-medium">{{ employee.name }}</span>
                                    <span class="block text-xs text-slate-500">{{ employee.staff_id }}</span>
                                </span>
                            </label>
                            <p v-if="employees.length === 0" class="text-sm text-slate-500">
                                No employees with login accounts found.
                            </p>
                        </div>
                        <p v-if="form.errors.employee_ids" class="mt-1 text-sm text-red-600">{{ form.errors.employee_ids }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <UiButton type="submit" variant="primary" :disabled="form.processing">
                            {{ editingId ? 'Save changes' : 'Create site' }}
                        </UiButton>
                        <UiButton v-if="editingId" type="button" variant="ghost" @click="resetForm">Cancel</UiButton>
                    </div>
                </form>
            </UiCard>
        </div>

        <div v-else class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
            <UiCard padding="none">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-slate-500 dark:border-slate-800 dark:bg-surface-elevated dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5 font-medium">Site</th>
                                <th class="px-5 py-3.5 font-medium">Access machine</th>
                                <th class="px-5 py-3.5 font-medium">Radius</th>
                                <th class="px-5 py-3.5 font-medium">Employees</th>
                                <th class="px-5 py-3.5 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="site in doorSites" :key="site.id!">
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ site.name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ site.latitude }}, {{ site.longitude }}
                                        <span v-if="!site.is_active"> · Inactive</span>
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ site.device_name ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ site.radius_meters }}m</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-400">{{ site.employees_count ?? site.employees?.length ?? 0 }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <UiButton v-if="can('self-punch-sites.update')" size="sm" variant="ghost" @click="editDoorSite(site)">Edit</UiButton>
                                        <UiButton v-if="can('self-punch-sites.delete')" size="sm" variant="danger" @click="destroyDoorSite(site.id!, site.name)">Delete</UiButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="doorSites.length === 0">
                                <td colspan="5" class="px-5 py-8 text-center text-slate-500">No remote door sites yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>

            <UiCard
                v-if="can('self-punch-sites.create') || can('self-punch-sites.update')"
                :title="editingDoorId ? 'Edit door site' : 'Add door site'"
                description="Link an access machine, set the geofence, and assign employees who may open the door remotely."
            >
                <form class="space-y-5" @submit.prevent="submitDoor">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Access machine</label>
                        <select
                            v-model="doorForm.zkt_device_id"
                            required
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm dark:border-slate-700 dark:bg-surface-elevated"
                        >
                            <option :value="null" disabled>Select access machine</option>
                            <option v-for="device in accessDevices" :key="device.id" :value="device.id">
                                {{ device.name }} ({{ device.connection_mode === 'adms_push' ? 'ADMS' : 'TCP' }})
                            </option>
                        </select>
                        <p v-if="doorForm.errors.zkt_device_id" class="mt-1 text-sm text-red-600">{{ doorForm.errors.zkt_device_id }}</p>
                        <p v-if="accessDevices.length === 0" class="mt-2 text-xs text-amber-600">Add an active access machine under Attendance Machines first (ADMS or TCP).</p>
                    </div>

                    <UiInput v-model="doorForm.name" label="Name" required :error="doorForm.errors.name" />
                    <UiInput v-model="doorForm.code" label="Code" :error="doorForm.errors.code" />
                    <UiInput v-model="doorForm.description" label="Description" :error="doorForm.errors.description" />

                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Location on map</p>
                        <GeoFenceMapPicker
                            :key="editingDoorId ?? 'door-new'"
                            v-model:latitude="doorForm.latitude"
                            v-model:longitude="doorForm.longitude"
                            v-model:radius-meters="doorForm.radius_meters"
                            :error="doorForm.errors.latitude || doorForm.errors.longitude || doorForm.errors.radius_meters"
                        />
                    </div>

                    <UiInput v-model="doorForm.max_accuracy_meters" label="Max GPS accuracy (m)" type="number" min="10" required :error="doorForm.errors.max_accuracy_meters" />
                    <UiInput v-model="doorForm.sort_order" label="Sort order" type="number" min="0" :error="doorForm.errors.sort_order" />

                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
                        <input v-model="doorForm.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                        Site is active
                    </label>

                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-surface-elevated">
                        <input v-model="doorForm.require_public_ip" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                        Require allowed public IP / CIDR
                    </label>

                    <div v-if="doorForm.require_public_ip">
                        <textarea
                            v-model="doorForm.allowed_public_ips_text"
                            rows="4"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm dark:border-slate-700 dark:bg-surface-elevated"
                            placeholder="203.0.113.10&#10;203.0.113.0/24"
                        />
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Allowed employees</p>
                        <div class="max-h-56 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <label v-for="employee in employees" :key="`door-${employee.id}`" class="flex items-start gap-3 rounded-lg px-2 py-2 text-sm">
                                <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-brand-600" :checked="doorForm.employee_ids.includes(employee.id)" @change="toggleDoorEmployee(employee.id)" />
                                <span>
                                    <span class="font-medium">{{ employee.name }}</span>
                                    <span class="block text-xs text-slate-500">{{ employee.staff_id }}</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <UiButton type="submit" variant="primary" :disabled="doorForm.processing">{{ editingDoorId ? 'Save changes' : 'Create door site' }}</UiButton>
                        <UiButton v-if="editingDoorId" type="button" variant="ghost" @click="resetDoorForm">Cancel</UiButton>
                    </div>
                </form>
            </UiCard>
        </div>
    </AppLayout>
</template>
