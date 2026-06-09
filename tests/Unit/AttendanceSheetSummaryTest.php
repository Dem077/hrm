<?php

use App\Enums\AttendanceDayStatus;
use App\Services\Attendance\AttendanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('summarizes attendance rows for dashboard widgets', function () {
    $service = app(AttendanceSheetService::class);

    $summary = $service->summarizeRows([
        ['status' => AttendanceDayStatus::Present->value, 'late_minutes' => null],
        ['status' => AttendanceDayStatus::Late->value, 'late_minutes' => 15],
        ['status' => AttendanceDayStatus::Incomplete->value, 'late_minutes' => null],
        ['status' => AttendanceDayStatus::Absent->value, 'late_minutes' => null],
        ['status' => AttendanceDayStatus::Leave->value, 'late_minutes' => null],
        ['status' => AttendanceDayStatus::Holiday->value, 'late_minutes' => null],
        ['status' => AttendanceDayStatus::Present->value, 'late_minutes' => 10],
    ]);

    expect($summary)->toBe([
        'present_days' => 4,
        'absent_days' => 1,
        'leave_days' => 1,
        'late_minutes' => 25,
    ]);
});
