<?php

namespace App\Jobs;

use App\Exceptions\ReportJobCancelledException;
use App\Models\ReportTemplate;
use App\Services\ReportGenerator;
use App\Services\Reports\ReportJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildTemplateReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public string $jobId,
        public int $templateId,
    ) {}

    public function handle(
        ReportGenerator $generator,
        ReportJobProgress $progress,
    ): void {
        try {
            $template = ReportTemplate::query()->find($this->templateId);

            if (! $template) {
                $progress->update($this->jobId, [
                    'status' => ReportJobProgress::STATUS_FAILED,
                    'error' => 'Report template not found.',
                    'message' => 'Failed',
                ]);

                return;
            }

            $progress->update($this->jobId, [
                'status' => ReportJobProgress::STATUS_RUNNING,
                'message' => 'Starting…',
            ]);

            $built = $generator->buildCsv(
                $template,
                function (int $done, int $total) use ($progress): void {
                    if ($progress->isCancelRequested($this->jobId)) {
                        throw new ReportJobCancelledException('Report job cancelled.');
                    }

                    $progress->update($this->jobId, [
                        'status' => ReportJobProgress::STATUS_RUNNING,
                        'done' => $done,
                        'total' => $total,
                        'message' => $total > 0
                            ? "Processing {$done} of {$total} rows…"
                            : 'No rows to export…',
                    ]);
                },
            );

            if ($progress->isCancelRequested($this->jobId)) {
                throw new ReportJobCancelledException('Report job cancelled.');
            }

            $progress->storeCsv($this->jobId, $built['contents'], $built['filename']);
            $progress->update($this->jobId, [
                'status' => ReportJobProgress::STATUS_COMPLETED,
                'percent' => 100,
                'message' => 'Completed',
                'error' => null,
                'download_ready' => true,
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
