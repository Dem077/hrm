<?php

namespace App\Http\Controllers;

use App\Jobs\BuildAttendanceReportJob;
use App\Services\Attendance\PayrollPeriodService;
use App\Services\Reports\AttendanceReportService;
use App\Services\Reports\ReportJobProgress;
use App\Support\StructureNodeOptions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function show(
        Request $request,
        PayrollPeriodService $payrollPeriodService,
    ): Response {
        [$from, $to, $departmentId, $payrollPeriod] = $this->resolveFilters($request, $payrollPeriodService);

        return Inertia::render('Reports/Attendance', [
            'rows' => [],
            'headers' => AttendanceReportService::headers(),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'department_id' => $departmentId,
                'payroll_period' => $request->input('payroll_period', '0'),
            ],
            'departments' => StructureNodeOptions::active(),
            'payrollPeriod' => $payrollPeriod,
        ]);
    }

    public function startJob(
        Request $request,
        PayrollPeriodService $payrollPeriodService,
        ReportJobProgress $progress,
    ) {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        [$from, $to, $departmentId] = $this->resolveFilters($request, $payrollPeriodService);

        $jobId = $progress->create('attendance_rows', $user->id, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'department_id' => $departmentId,
        ]);

        BuildAttendanceReportJob::dispatch($jobId, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'department_id' => $departmentId,
        ], 'rows');

        return response()->json([
            'job_id' => $jobId,
        ]);
    }

    public function result(string $jobId, ReportJobProgress $progress, Request $request)
    {
        $payload = $progress->get($jobId);

        if ($payload === null || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()?->id) {
            abort(404, 'Report job not found.');
        }

        if (($payload['status'] ?? null) !== ReportJobProgress::STATUS_COMPLETED) {
            abort(409, 'Report is not ready yet.');
        }

        return response()->json([
            'rows' => $progress->getRows($jobId) ?? [],
        ]);
    }

    public function startDownloadJob(
        Request $request,
        PayrollPeriodService $payrollPeriodService,
        ReportJobProgress $progress,
    ) {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        [$from, $to, $departmentId] = $this->resolveFilters($request, $payrollPeriodService);

        $jobId = $progress->create('attendance_download', $user->id, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'department_id' => $departmentId,
        ]);

        BuildAttendanceReportJob::dispatch($jobId, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'department_id' => $departmentId,
        ], 'download');

        return response()->json([
            'job_id' => $jobId,
        ]);
    }

    public function download(
        Request $request,
        AttendanceReportService $reportService,
        PayrollPeriodService $payrollPeriodService,
    ): StreamedResponse {
        [$from, $to, $departmentId] = $this->resolveFilters($request, $payrollPeriodService);

        return $reportService->download($from, $to, $departmentId);
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon, 2: ?int, 3: array<string, mixed>}
     */
    protected function resolveFilters(Request $request, PayrollPeriodService $payrollPeriodService): array
    {
        $timezone = config('app.timezone', 'UTC');
        $payrollPeriod = $payrollPeriodService->presentation();
        $departmentId = $request->filled('department_id')
            ? $request->integer('department_id')
            : null;

        $payrollPeriodKey = (string) $request->input('payroll_period', '0');

        if ($payrollPeriodKey === 'custom' && ($request->filled('from') || $request->filled('to'))) {
            $from = $request->filled('from')
                ? \Illuminate\Support\Carbon::parse($request->string('from')->toString(), $timezone)->startOfDay()
                : \Illuminate\Support\Carbon::parse($payrollPeriod['current']['from'], $timezone)->startOfDay();

            $to = $request->filled('to')
                ? \Illuminate\Support\Carbon::parse($request->string('to')->toString(), $timezone)->startOfDay()
                : $from->copy();
        } elseif ($payrollPeriodKey === 'current') {
            $from = \Illuminate\Support\Carbon::parse($payrollPeriod['current']['from'], $timezone)->startOfDay();
            $to = \Illuminate\Support\Carbon::parse($payrollPeriod['current']['to'], $timezone)->startOfDay();
        } elseif ($payrollPeriodKey === 'previous') {
            $from = \Illuminate\Support\Carbon::parse($payrollPeriod['previous']['from'], $timezone)->startOfDay();
            $to = \Illuminate\Support\Carbon::parse($payrollPeriod['previous']['to'], $timezone)->startOfDay();
        } elseif (ctype_digit($payrollPeriodKey)) {
            $period = $payrollPeriodService->recentPeriodByOffset((int) $payrollPeriodKey)
                ?? $payrollPeriodService->recentPeriodByOffset(0);

            $from = $period['from'];
            $to = $period['to'];
        } else {
            $from = \Illuminate\Support\Carbon::parse($payrollPeriod['current']['from'], $timezone)->startOfDay();
            $to = \Illuminate\Support\Carbon::parse($payrollPeriod['current']['to'], $timezone)->startOfDay();
        }

        return [$from, $to, $departmentId, $payrollPeriod];
    }
}
