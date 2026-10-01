<script setup lang="ts">
import { computed } from 'vue';

import JobProgressModal from '@/components/job-progress/JobProgressModal.vue';
import type { JobProgressPayload } from '@/components/job-progress/useJobProgress';

const props = defineProps<{
    open: boolean;
    progress: JobProgressPayload | null;
    percent: number;
    message: string;
    error: string | null;
    canCancel: boolean;
    cancelling: boolean;
    isTerminal: boolean;
}>();

defineEmits<{
    close: [];
    cancel: [];
}>();

const title = computed(() => {
    const action = props.progress?.action;

    if (action === 'export') {
        return 'Exporting payroll';
    }

    if (action === 'bulk_adjust') {
        return 'Bulk adjustment';
    }

    return 'Payroll job';
});

const description = computed(() => {
    const action = props.progress?.action;

    if (action === 'export') {
        return 'Building the spreadsheet in the background. You can cancel before the download is ready.';
    }

    if (action === 'bulk_adjust') {
        return 'Applying the adjustment to selected employees. You can cancel before it finishes.';
    }

    return 'Building payroll for all employees. You can cancel before it finishes saving.';
});

const unitLabel = computed(() => {
    const action = props.progress?.action;

    if (action === 'export' || action === 'bulk_adjust') {
        return 'employees';
    }

    return 'employees';
});
</script>

<template>
    <JobProgressModal
        :open="open"
        :progress="progress"
        :percent="percent"
        :message="message"
        :error="error"
        :can-cancel="canCancel"
        :cancelling="cancelling"
        :is-terminal="isTerminal"
        :title="title"
        :description="description"
        :unit-label="unitLabel"
        @cancel="$emit('cancel')"
        @close="$emit('close')"
    />
</template>
