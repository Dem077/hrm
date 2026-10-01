<script setup lang="ts">
import JobProgressModal from '@/components/job-progress/JobProgressModal.vue';
import type { JobProgressPayload } from '@/components/job-progress/useJobProgress';

withDefaults(
    defineProps<{
        open: boolean;
        progress: JobProgressPayload | null;
        percent: number;
        message: string;
        error: string | null;
        canCancel: boolean;
        cancelling: boolean;
        isTerminal: boolean;
        unitLabel?: string;
    }>(),
    {
        unitLabel: 'chunks',
    },
);

defineEmits<{
    close: [];
    cancel: [];
}>();
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
        title="Report job"
        description="Building the report. You can cancel before it finishes."
        :unit-label="unitLabel"
        @cancel="$emit('cancel')"
        @close="$emit('close')"
    />
</template>
