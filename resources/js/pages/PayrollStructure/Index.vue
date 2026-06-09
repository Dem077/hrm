<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import DesignationsCard from '@/pages/PayrollStructure/components/DesignationsCard.vue';
import PayrollComponentsCard from '@/pages/PayrollStructure/components/PayrollComponentsCard.vue';
import type { Designation, DesignationPayrollItem, PayrollComponent } from '@/types/payroll';

defineProps<{
    components: PayrollComponent[];
    designations: Designation[];
    emptyComponent: PayrollComponent;
    emptyDesignation: Designation;
    defaultDesignationItems: DesignationPayrollItem[];
}>();

const { can } = usePermissions();

const canManage = can('payroll-structure.update');

function destroyComponent(id: number, name: string) {
    if (confirm(`Delete payroll component "${name}"?`)) {
        router.delete(`/payroll-structure/components/${id}`, { preserveScroll: true });
    }
}

function destroyDesignation(id: number, name: string) {
    if (confirm(`Delete designation "${name}"?`)) {
        router.delete(`/payroll-structure/designations/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Payroll Structure" />

    <AppLayout>
        <PageHeader
            title="Payroll structure"
            description="Define payroll components, then build salary packages per designation with fixed amounts and daily rates."
        />

        <div class="space-y-8">
            <PayrollComponentsCard
                :components="components"
                :empty-component="emptyComponent"
                :can-manage="canManage"
                @delete="destroyComponent"
            />

            <DesignationsCard
                :designations="designations"
                :components="components"
                :empty-designation="emptyDesignation"
                :default-designation-items="defaultDesignationItems"
                :can-manage="canManage"
                @delete="destroyDesignation"
            />
        </div>
    </AppLayout>
</template>
