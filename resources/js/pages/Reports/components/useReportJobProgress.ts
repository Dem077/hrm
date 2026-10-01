import { useJobProgress } from '@/components/job-progress/useJobProgress';

export type { JobProgressPayload as ReportJobProgressPayload } from '@/components/job-progress/useJobProgress';

export function useReportJobProgress() {
    return useJobProgress({
        statusUrl: (jobId) => `/reports/jobs/${jobId}`,
        cancelUrl: (jobId) => `/reports/jobs/${jobId}/cancel`,
        channel: (jobId) => `report-job.${jobId}`,
        event: '.ReportJobProgressUpdated',
        failedMessage: 'Report job failed.',
    });
}
