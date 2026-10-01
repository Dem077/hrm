<?php

namespace App\Jobs;

use App\Services\Payroll\PayrollRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BulkAdjustPayrollRunJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  list<int>  $employeeIds
     */
    public function __construct(
        public string $jobId,
        public int $payrollRunId,
        public int $userId,
        public array $employeeIds,
        public string $type,
        public string $title,
        public float $amount,
        public ?string $remarks,
    ) {}

    public function handle(PayrollRunService $payrollRunService): void
    {
        $payrollRunService->executeBulkAdjustJob(
            $this->jobId,
            $this->payrollRunId,
            $this->userId,
            $this->employeeIds,
            $this->type,
            $this->title,
            $this->amount,
            $this->remarks,
        );
    }
}
