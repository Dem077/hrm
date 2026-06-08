<?php

namespace App\Http\Requests\Concerns;

use App\Services\Leave\LeaveRequestService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

trait ValidatesLeaveDateOverlap
{
    protected function validateLeaveDateOverlap(Validator $validator, int $employeeId): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if (! $this->filled('start_date') || ! $this->filled('end_date')) {
            return;
        }

        $timezone = config('app.timezone', 'UTC');
        $startDate = Carbon::parse($this->input('start_date'), $timezone)->startOfDay();
        $endDate = Carbon::parse($this->input('end_date'), $timezone)->startOfDay();

        $leaveService = app(LeaveRequestService::class);
        $overlap = $leaveService->findOverlappingLeave($employeeId, $startDate, $endDate);

        if (! $overlap) {
            return;
        }

        $message = $leaveService->overlappingLeaveMessage($overlap);

        $validator->errors()->add('start_date', $message);
        $validator->errors()->add('end_date', $message);
    }
}
