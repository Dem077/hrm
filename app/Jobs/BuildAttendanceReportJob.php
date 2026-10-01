<?php

namespace App\Jobs;

use App\Exceptions\ReportJobCancelledException;
use App\Services\Reports\AttendanceReportService;
use App\Services\Reports\ReportJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class BuildAttendanceReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  array{from: string, to: string, department_id: ?int}  $filters
     */
    public function __construct(
        public string $jobId,
        public array $filters,
        public string $mode = 'rows',
    ) {}

    public function handle(
        AttendanceReportService $reportService,
        ReportJobProgress $progress,
    ): void {
        try {
            $progress->update($this->jobId, [
                'status' => ReportJobProgress::STATUS_RUNNING,
                'message' => 'Starting…',
            ]);

            $from = Carbon::parse($this->filters['from'])->startOfDay();
            $to = Carbon::parse($this->filters['to'])->startOfDay();
            $departmentId = $this->filters['department_id'] ?? null;

            $onProgress = function (int $done, int $total) use ($progress): void {
                if ($progress->isCancelRequested($this->jobId)) {
                    throw new ReportJobCancelledException('Report job cancelled.');
                }

                $progress->update($this->jobId, [
                    'status' => ReportJobProgress::STATUS_RUNNING,
                    'done' => $done,
                    'total' => $total,
                    'message' => $total > 0
                        ? "Processing {$done} of {$total} date chunks…"
                        : 'No date ranges to process…',
                ]);
            };

            if ($this->mode === 'download') {
                $built = $reportService->buildCsv($from, $to, $departmentId, $onProgress);

                if ($progress->isCancelRequested($this->jobId)) {
                    throw new ReportJobCancelledException('Report job cancelled.');
                }

                $progress->storeCsv($this->jobId, $built['contents'], $built['filename']);
            } else {
                $rows = $reportService->rows($from, $to, $departmentId, $onProgress);

                if ($progress->isCancelRequested($this->jobId)) {
                    throw new ReportJobCancelledException('Report job cancelled.');
                }

                $progress->storeRows($this->jobId, $rows);
            }

            $progress->update($this->jobId, [
                'status' => ReportJobProgress::STATUS_COMPLETED,
                'percent' => 100,
                'message' => 'Completed',
                'error' => null,
                'download_ready' => $this->mode === 'download',
            ]);
        } catch (ReportJobCancelledException) {
            $progress->deleteArtifacts($this->jobId);
            $progress->update($this->jobId, [
                'status' => ReportJobProgress::STATUS_CANCELLED,
                'message' => 'Cancelled',
                'download_ready' => false,
            ]);
        } catch (\Throwable $e) {
            $progress->deleteArtifacts($this->jobId);
            $progress->update($this->jobId, [
                'status' => ReportJobProgress::STATUS_FAILED,
                'error' => $e->getMessage(),
                'message' => 'Failed',
                'download_ready' => false,
            ]);

            throw $e;
        }
    }
}
