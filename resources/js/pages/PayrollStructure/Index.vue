<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';

import PageHeader from '@/components/ui/PageHeader.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import GradesCard from '@/pages/PayrollStructure/components/GradesCard.vue';
import PayrollComponentsCard from '@/pages/PayrollStructure/components/PayrollComponentsCard.vue';
import type { LoanBankOption, PayrollComponent, StructureGradePackage } from '@/types/payroll';

defineProps<{
    components: PayrollComponent[];
    grades: StructureGradePackage[];
    emptyComponent: PayrollComponent;
    loanBanks: LoanBankOption[];
}>();

const { can } = usePermissions();

const canManage = can('payroll-structure.update');

function destroyComponent(id: number, name: string) {
    if (confirm(`Delete payroll component "${name}"?`)) {
        router.delete(`/payroll-structure/components/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Payroll Structure" />

    <AppLayout>
        <PageHeader
            title="Payroll structure"
            description="Define payroll components, then build salary packages per grade with fixed amounts and daily rates."
        />

        <div class="space-y-8">
            <PayrollComponentsCard
                :components="components"
                :empty-component="emptyComponent"
                :can-manage="canManage"
                @delete="destroyComponent"
            />

            <GradesCard
                :grades="grades"
                :components="components"
                :loan-banks="loanBanks"
                :can-manage="canManage"
            />
        </div>
    </AppLayout>
</template>
