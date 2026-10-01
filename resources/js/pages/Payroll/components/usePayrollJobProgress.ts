import { useJobProgress } from '@/components/job-progress/useJobProgress';

export type { JobProgressPayload as PayrollJobProgressPayload } from '@/components/job-progress/useJobProgress';

export function usePayrollJobProgress() {
    return useJobProgress({
        statusUrl: (jobId) => `/payroll/jobs/${jobId}`,
        cancelUrl: (jobId) => `/payroll/jobs/${jobId}/cancel`,
        channel: (jobId) => `payroll-job.${jobId}`,
        event: '.PayrollJobProgressUpdated',
        failedMessage: 'Payroll job failed.',
    });
}
