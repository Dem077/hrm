<?php

use App\Services\Payroll\PayrollJobProgress;
use App\Services\Reports\ReportJobProgress;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('payroll-job.{jobId}', function ($user, string $jobId) {
    $progress = app(PayrollJobProgress::class)->get($jobId);

    if ($progress === null) {
        return false;
    }

    return (int) ($progress['user_id'] ?? 0) === (int) $user->id;
});

Broadcast::channel('report-job.{jobId}', function ($user, string $jobId) {
    $progress = app(ReportJobProgress::class)->get($jobId);

    if ($progress === null) {
        return false;
    }

    return (int) ($progress['user_id'] ?? 0) === (int) $user->id;
});
