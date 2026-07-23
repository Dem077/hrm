<?php

use App\Enums\AttendanceDayStatus;
use App\Services\Attendance\AttendanceSheetService;
use App\Services\Attendance\PayrollPeriodService;
use App\Services\Payroll\PayrollFormulaEvaluator;
use App\Services\Payroll\PayrollProcessingService;
use Illuminate\Support\Carbon;

it('builds formula variables from actual attendance figures and payroll period length', function () {
    $service = new PayrollProcessingService(
        app(AttendanceSheetService::class),
        app(PayrollPeriodService::class),
        app(PayrollFormulaEvaluator::class),
    );

    $from = Carbon::parse('2026-06-01');
    $to = Carbon::parse('2026-06-10'); // 10 inclusive days

    $rows = [
        [
            'status' => AttendanceDayStatus::Present->value,
            'late_minutes' => 15,
            'working_minutes' => 540, // 9h vs 8h duty = 1h extra
            'duty_start_time' => '09:00',
            'duty_end_time' => '17:00',
        ],
        [
            'status' => AttendanceDayStatus::Late->value,
            'late_minutes' => 20,
            'working_minutes' => 480, // exactly 8h
            'duty_start_time' => '09:00',
            'duty_end_time' => '17:00',
        ],
        [
            'status' => AttendanceDayStatus::Absent->value,
            'late_minutes' => null,
            'working_minutes' => null,
            'duty_start_time' => '09:00',
            'duty_end_time' => '17:00',
        ],
        [
            'status' => AttendanceDayStatus::Holiday->value,
            'late_minutes' => null,
            'working_minutes' => null,
            'duty_start_time' => '—',
            'duty_end_time' => '—',
        ],
        [
            'status' => AttendanceDayStatus::Present->value,
            'late_minutes' => null,
            'working_minutes' => 600, // 10h vs 8h = 2h extra
            'duty_start_time' => '09:00',
            'duty_end_time' => '17:00',
        ],
    ];

    $variables = $service->buildFormulaVariables($from, $to, $rows, 30000.0);

    expect($variables)->toMatchArray([
        'absent_days' => 1,
        'present_days' => 3, // Present + Late (+ Incomplete if any)
        'late_minutes' => 35,
        'basic_salary' => 30000.0,
        'hours_worked' => 27.0, // (540+480+600)/60
        'additional_hours_worked' => 3.0, // 1h + 0h + 2h
        'working_days' => 4, // excludes holiday
        'total_days_of_payroll' => 10, // payroll period length, not calendar month
    ]);
});
