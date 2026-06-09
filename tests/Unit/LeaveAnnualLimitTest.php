<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createEmployeeForLeaveTests(string $joinedDate = '2024-03-15'): Employee
{
    return Employee::query()->create([
        'staff_id' => 'EMP-'.uniqid(),
        'name' => 'Test Employee',
        'national_id' => 'NID-'.uniqid(),
        'joined_date' => $joinedDate,
        'gender' => 'male',
        'is_active' => true,
    ]);
}

function createLeaveRequestForTests(
    Employee $employee,
    LeaveType $leaveType,
    string $startDate,
    string $endDate,
    int $daysCount,
    LeaveRequestStatus $status,
    string $reason = 'Test leave',
): LeaveRequest {
    return LeaveRequest::query()->create([
        'record_number' => 'HR/2026/FORM/'.uniqid(),
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'days_count' => $daysCount,
        'reason' => $reason,
        'status' => $status,
    ]);
}

it('calculates anniversary year bounds from joining date', function () {
    $employee = createEmployeeForLeaveTests('2024-03-15');
    $service = app(LeaveRequestService::class);

    [$start, $end] = $service->anniversaryYearBounds($employee, Carbon::parse('2026-06-08'));

    expect($start->toDateString())->toBe('2026-03-15')
        ->and($end->toDateString())->toBe('2027-03-14');

    [$start, $end] = $service->anniversaryYearBounds($employee, Carbon::parse('2026-02-01'));

    expect($start->toDateString())->toBe('2025-03-15')
        ->and($end->toDateString())->toBe('2026-03-14');
});

it('counts used leave days within an anniversary period', function () {
    $employee = createEmployeeForLeaveTests('2024-03-15');
    $leaveType = LeaveType::query()->create([
        'name' => 'Annual Leave',
        'annual_limit' => 10,
    ]);

    createLeaveRequestForTests(
        $employee,
        $leaveType,
        '2026-04-01',
        '2026-04-03',
        3,
        LeaveRequestStatus::Approved,
        'Existing leave',
    );

    $service = app(LeaveRequestService::class);
    [$periodStart, $periodEnd] = $service->anniversaryYearBounds($employee, Carbon::parse('2026-06-08'));

    expect($service->usedLeaveDaysInAnniversaryYear(
        $employee->id,
        $leaveType->id,
        $periodStart,
        $periodEnd,
    ))->toBe(3);
});

it('does not count pending leave as used leave days', function () {
    $employee = createEmployeeForLeaveTests('2024-03-15');
    $leaveType = LeaveType::query()->create([
        'name' => 'Annual Leave',
        'annual_limit' => 5,
    ]);

    createLeaveRequestForTests(
        $employee,
        $leaveType,
        '2026-04-01',
        '2026-04-05',
        5,
        LeaveRequestStatus::Pending,
        'Pending leave',
    );

    $service = app(LeaveRequestService::class);
    [$periodStart, $periodEnd] = $service->anniversaryYearBounds($employee, Carbon::parse('2026-06-08'));

    expect($service->usedLeaveDaysInAnniversaryYear(
        $employee->id,
        $leaveType->id,
        $periodStart,
        $periodEnd,
    ))->toBe(0);
});

it('includes pending leave when checking the annual limit', function () {
    $employee = createEmployeeForLeaveTests('2024-03-15');
    $leaveType = LeaveType::query()->create([
        'name' => 'Annual Leave',
        'annual_limit' => 5,
    ]);

    createLeaveRequestForTests(
        $employee,
        $leaveType,
        '2026-04-01',
        '2026-04-05',
        5,
        LeaveRequestStatus::Pending,
        'Pending leave',
    );

    $service = app(LeaveRequestService::class);

    expect($service->wouldExceedAnnualLimit(
        $employee,
        $leaveType,
        Carbon::parse('2026-05-01'),
        Carbon::parse('2026-05-01'),
    ))->toBeTrue();
});

it('allows leave when annual limit is not set', function () {
    $employee = createEmployeeForLeaveTests();
    $leaveType = LeaveType::query()->create([
        'name' => 'Unlimited Leave',
        'annual_limit' => null,
    ]);

    $service = app(LeaveRequestService::class);

    expect($service->wouldExceedAnnualLimit(
        $employee,
        $leaveType,
        Carbon::parse('2026-05-01'),
        Carbon::parse('2026-05-10'),
    ))->toBeFalse();
});

it('attributes cross-year leave entirely to the start date leave year', function () {
    $employee = createEmployeeForLeaveTests('2024-03-15');
    $leaveType = LeaveType::query()->create([
        'name' => 'Annual Leave',
        'annual_limit' => 5,
    ]);

    $service = app(LeaveRequestService::class);

    expect($service->wouldExceedAnnualLimit(
        $employee,
        $leaveType,
        Carbon::parse('2026-03-13'),
        Carbon::parse('2026-03-18'),
    ))->toBeTrue();

    expect($service->wouldExceedAnnualLimit(
        $employee,
        $leaveType,
        Carbon::parse('2026-03-13'),
        Carbon::parse('2026-03-16'),
    ))->toBeFalse();
});

it('does not count cross-year leave against the next leave year', function () {
    $employee = createEmployeeForLeaveTests('2024-03-15');
    $leaveType = LeaveType::query()->create([
        'name' => 'Annual Leave',
        'annual_limit' => 5,
    ]);

    createLeaveRequestForTests(
        $employee,
        $leaveType,
        '2026-03-13',
        '2026-03-18',
        6,
        LeaveRequestStatus::Approved,
        'Cross-year leave',
    );

    $service = app(LeaveRequestService::class);
    [$nextPeriodStart, $nextPeriodEnd] = $service->anniversaryYearBounds($employee, Carbon::parse('2026-03-16'));

    expect($service->usedLeaveDaysInAnniversaryYear(
        $employee->id,
        $leaveType->id,
        $nextPeriodStart,
        $nextPeriodEnd,
    ))->toBe(0);
});

it('lists leave year options back to joining date', function () {
    $employee = createEmployeeForLeaveTests('2024-03-15');
    $service = app(LeaveRequestService::class);

    $options = $service->leaveYearOptionsForEmployee($employee);

    expect($options)->not->toBeEmpty()
        ->and($options[0]['is_current'])->toBeTrue()
        ->and($options[0]['offset'])->toBe(0);

    $last = $options[array_key_last($options)];

    expect($last['period_start'])->toBe('2024-03-15');
});

it('calculates leave year bounds by offset', function () {
    $employee = createEmployeeForLeaveTests('2024-03-15');
    $service = app(LeaveRequestService::class);

    [$start, $end] = $service->leaveYearBoundsByOffset($employee, 1);

    expect($start->toDateString())->toBe('2025-03-15')
        ->and($end->toDateString())->toBe('2026-03-14');
});

it('rejects leave when the start date leave year is already exhausted', function () {
    $employee = createEmployeeForLeaveTests('2024-03-15');
    $leaveType = LeaveType::query()->create([
        'name' => 'Annual Leave',
        'annual_limit' => 3,
    ]);

    createLeaveRequestForTests(
        $employee,
        $leaveType,
        '2026-03-10',
        '2026-03-12',
        3,
        LeaveRequestStatus::Approved,
        'Used in previous period',
    );

    $service = app(LeaveRequestService::class);

    expect($service->wouldExceedAnnualLimit(
        $employee,
        $leaveType,
        Carbon::parse('2026-03-13'),
        Carbon::parse('2026-03-16'),
    ))->toBeTrue();
});
