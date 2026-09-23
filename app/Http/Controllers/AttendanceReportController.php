<?php

namespace App\Http\Controllers;

use App\Services\Attendance\PayrollPeriodService;
use App\Services\Reports\AttendanceReportService;
use App\Support\StructureNodeOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function show(
        Request $request,
        AttendanceReportService $reportService,
        PayrollPeriodService $payrollPeriodService,
    ): Response {
        [$from, $to, $departmentId, $payrollPeriod] = $this->resolveFilters($request, $payrollPeriodService);

        return Inertia::render('Reports/Attendance', [
            'rows' => $reportService->rows($from, $to, $departmentId),
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

    public function download(
        Request $request,
        AttendanceReportService $reportService,
        PayrollPeriodService $payrollPeriodService,
    ): StreamedResponse {
        [$from, $to, $departmentId] = $this->resolveFilters($request, $payrollPeriodService);

        return $reportService->download($from, $to, $departmentId);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: ?int, 3: array<string, mixed>}
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
                ? Carbon::parse($request->string('from')->toString(), $timezone)->startOfDay()
                : Carbon::parse($payrollPeriod['current']['from'], $timezone)->startOfDay();

            $to = $request->filled('to')
                ? Carbon::parse($request->string('to')->toString(), $timezone)->startOfDay()
                : $from->copy();
        } elseif ($payrollPeriodKey === 'current') {
            $from = Carbon::parse($payrollPeriod['current']['from'], $timezone)->startOfDay();
            $to = Carbon::parse($payrollPeriod['current']['to'], $timezone)->startOfDay();
        } elseif ($payrollPeriodKey === 'previous') {
            $from = Carbon::parse($payrollPeriod['previous']['from'], $timezone)->startOfDay();
            $to = Carbon::parse($payrollPeriod['previous']['to'], $timezone)->startOfDay();
        } elseif (ctype_digit($payrollPeriodKey)) {
            $period = $payrollPeriodService->recentPeriodByOffset((int) $payrollPeriodKey)
                ?? $payrollPeriodService->recentPeriodByOffset(0);

            $from = $period['from'];
            $to = $period['to'];
        } else {
            $from = Carbon::parse($payrollPeriod['current']['from'], $timezone)->startOfDay();
            $to = Carbon::parse($payrollPeriod['current']['to'], $timezone)->startOfDay();
        }

        return [$from, $to, $departmentId, $payrollPeriod];
    }
}
