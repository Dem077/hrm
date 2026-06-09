<?php

namespace App\Http\Controllers;

use App\Services\Attendance\AttendanceSheetService;
use App\Services\Attendance\PayrollPeriodService;
use App\Services\Leave\LeaveRequestService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        PayrollPeriodService $payrollPeriodService,
        AttendanceSheetService $attendanceSheetService,
        LeaveRequestService $leaveRequestService,
    ): Response {
        $timezone = config('app.timezone', 'UTC');
        $employee = request()->user()?->employee;
        $period = $payrollPeriodService->currentPeriod();
        $today = now($timezone)->startOfDay();
        $periodEnd = $period['to']->gt($today) ? $today : $period['to'];

        $attendance = null;
        $leaveBalance = null;

        if ($employee) {
            $result = $attendanceSheetService->build(
                $period['from'],
                $periodEnd,
                employeeId: $employee->id,
            );

            $attendance = [
                'period_label' => $period['label'],
                ...$attendanceSheetService->summarizeRows($result['rows']),
            ];

            $balanceReport = $leaveRequestService->buildEmployeeLeaveBalance($employee);

            $leaveBalance = [
                'leave_year_label' => $balanceReport['selectedLeaveYear']['label'],
                'balances' => $balanceReport['employee']['balances'],
            ];
        }

        return Inertia::render('Dashboard', [
            'hasEmployeeProfile' => $employee !== null,
            'attendance' => $attendance,
            'leaveBalance' => $leaveBalance,
        ]);
    }
}
