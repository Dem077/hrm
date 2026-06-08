<?php

use App\Models\AttendanceGeneralSetting;
use App\Services\Attendance\PayrollPeriodService;
use Illuminate\Support\Carbon;

it('calculates the current payroll period before the start day', function () {
    AttendanceGeneralSetting::query()->create(['payroll_period_start_day' => 25]);

    $period = app(PayrollPeriodService::class)->periodContaining(
        Carbon::parse('2026-06-08', 'Asia/Karachi'),
    );

    expect($period['from']->toDateString())->toBe('2026-05-25');
    expect($period['to']->toDateString())->toBe('2026-06-24');
});

it('calculates the current payroll period on or after the start day', function () {
    AttendanceGeneralSetting::query()->create(['payroll_period_start_day' => 25]);

    $period = app(PayrollPeriodService::class)->periodContaining(
        Carbon::parse('2026-06-26', 'Asia/Karachi'),
    );

    expect($period['from']->toDateString())->toBe('2026-06-25');
    expect($period['to']->toDateString())->toBe('2026-07-24');
});

it('returns the last six payroll periods', function () {
    AttendanceGeneralSetting::query()->create(['payroll_period_start_day' => 25]);

    $recent = app(PayrollPeriodService::class)->recentPeriods(
        6,
        Carbon::parse('2026-06-08', 'Asia/Karachi'),
    );

    expect($recent)->toHaveCount(6);
    expect($recent[0]['is_current'])->toBeTrue();
    expect($recent[0]['from'])->toBe('2026-05-25');
    expect($recent[0]['to'])->toBe('2026-06-24');
    expect($recent[1]['from'])->toBe('2026-04-25');
    expect($recent[2]['from'])->toBe('2026-03-25');
    expect($recent[5]['from'])->toBe('2025-12-25');
});
