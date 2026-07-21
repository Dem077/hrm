<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

import UiBadge from '@/components/ui/UiBadge.vue';
import UiButton from '@/components/ui/UiButton.vue';
import UiCard from '@/components/ui/UiCard.vue';
import UiInput from '@/components/ui/UiInput.vue';
import UiModal from '@/components/ui/UiModal.vue';

export type BankRow = {
    id: number;
    code: string;
    name: string;
    sort_order: number;
    is_active: boolean;
    in_use: boolean;
};

const props = defineProps<{
    banks: BankRow[];
    canManage: boolean;
}>();

const modalOpen = ref(false);
const editing = ref<BankRow | null>(null);

const form = useForm({
    code: '',
    name: '',
    sort_order: 0,
    is_active: true,
});

const localErrors = reactive<{ bank?: string }>({});

function openCreate() {
    editing.value = null;
    form.clearErrors();
    form.reset();
    form.code = '';
    form.name = '';
    form.sort_order = (props.banks.at(-1)?.sort_order ?? -1) + 1;
    form.is_active = true;
    modalOpen.value = true;
}

function openEdit(bank: BankRow) {
    editing.value = bank;
    form.clearErrors();
    form.code = bank.code;
    form.name = bank.name;
    form.sort_order = bank.sort_order;
    form.is_active = bank.is_active;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function save() {
    if (editing.value) {
        form.put(`/attendance-settings/banks/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
        return;
    }

    form.post('/attendance-settings/banks', {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
}

function removeBank(bank: BankRow) {
    localErrors.bank = undefined;

    if (bank.in_use) {
        localErrors.bank = 'This bank is in use. Deactivate it instead of deleting.';
        return;
    }

    if (!confirm(`Delete bank ${bank.name}?`)) {
        return;
    }

    router.delete(`/attendance-settings/banks/${bank.id}`, {
        preserveScroll: true,
        onError: (errors) => {
            localErrors.bank = errors.bank;
        },
    });
}
</script>

<template>
    <UiCard title="Banks" description="Banks available for employee accounts and payroll loan packages.">
        <template v-if="canManage" #actions>
            <UiButton type="button" size="sm" variant="secondary" @click="openCreate">Add bank</UiButton>
        </template>

        <p v-if="localErrors.bank" class="mb-3 text-sm text-red-600">{{ localErrors.bank }}</p>

        <div v-if="banks.length === 0" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
            No banks yet. Add one to use in employee and payroll forms.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Status</th>
                        <th v-if="canManage" class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="bank in banks" :key="bank.id">
                        <td class="px-3 py-2 font-medium">{{ bank.code }}</td>
                        <td class="px-3 py-2">{{ bank.name }}</td>
                        <td class="px-3 py-2">
                            <UiBadge
                                :label="bank.is_active ? 'Active' : 'Inactive'"
                                :color="bank.is_active ? 'success' : 'gray'"
                            />
                        </td>
                        <td v-if="canManage" class="px-3 py-2">
                            <div class="flex items-center justify-end gap-2">
                                <UiButton type="button" size="sm" variant="ghost" @click="openEdit(bank)">
                                    Edit
                                </UiButton>
                                <UiButton
                                    type="button"
                                    size="sm"
                                    variant="danger"
                                    :disabled="bank.in_use"
                                    @click="removeBank(bank)"
                                >
                                    Delete
                                </UiButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <UiModal
            :open="modalOpen"
            :title="editing ? 'Edit bank' : 'Add bank'"
            description="Bank codes are stored on employees and payroll packages."
            max-width="md"
            @close="closeModal"
        >
            <div class="space-y-4">
                <UiInput v-model="form.code" label="Code" placeholder="BML" required :error="form.errors.code" />
                <UiInput
                    v-model="form.name"
                    label="Display name"
                    placeholder="Bank of Maldives (BML)"
                    required
                    :error="form.errors.name"
                />
                <UiInput
                    v-model.number="form.sort_order"
                    type="number"
                    label="Sort order"
                    :error="form.errors.sort_order"
                />
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                    Active (shown in dropdowns)
                </label>
            </div>
            <template #footer>
                <UiButton type="button" variant="ghost" @click="closeModal">Cancel</UiButton>
                <UiButton type="button" variant="primary" :disabled="form.processing" @click="save">
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </UiButton>
            </template>
        </UiModal>
    </UiCard>
</template>
