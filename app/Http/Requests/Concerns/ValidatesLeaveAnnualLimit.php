<?php

namespace App\Http\Requests\Concerns;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Leave\LeaveRequestService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

trait ValidatesLeaveAnnualLimit
{
    protected function validateLeaveAnnualLimit(Validator $validator, int $employeeId): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if (! $this->filled('start_date') || ! $this->filled('end_date') || ! $this->filled('leave_type_id')) {
            return;
        }

        $employee = Employee::query()->find($employeeId);
        $leaveType = LeaveType::query()->find($this->integer('leave_type_id'));

        if (! $employee || ! $leaveType || $leaveType->annual_limit === null) {
            return;
        }

        $timezone = config('app.timezone', 'UTC');
        $startDate = Carbon::parse($this->input('start_date'), $timezone)->startOfDay();
        $endDate = Carbon::parse($this->input('end_date'), $timezone)->startOfDay();

        $leaveService = app(LeaveRequestService::class);

        if (! $leaveService->wouldExceedAnnualLimit($employee, $leaveType, $startDate, $endDate)) {
            return;
        }

        $message = $leaveService->exceedAnnualLimitMessage($employee, $leaveType, $startDate, $endDate);

        $validator->errors()->add('leave_type_id', $message);
        $validator->errors()->add('start_date', $message);
        $validator->errors()->add('end_date', $message);
    }
}
