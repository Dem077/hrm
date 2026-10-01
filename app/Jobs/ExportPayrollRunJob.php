<?php

namespace App\Jobs;

use App\Services\Payroll\PayrollRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExportPayrollRunJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public string $jobId,
        public int $payrollRunId,
    ) {}

    public function handle(PayrollRunService $payrollRunService): void
    {
        $payrollRunService->executeExportJob($this->jobId, $this->payrollRunId);
    }
}
