<?php

namespace App\Http\Requests\Concerns;

use App\Services\Leave\LeaveRequestService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

trait ValidatesLeavePunchOverlap
{
    protected function validateLeavePunchOverlap(Validator $validator, int $employeeId): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($this->boolean('acknowledge_punch_overlap')) {
            return;
        }

        if (! $this->filled('start_date') || ! $this->filled('end_date')) {
            return;
        }

        $timezone = config('app.timezone', 'UTC');
        $startDate = Carbon::parse($this->input('start_date'), $timezone)->startOfDay();
        $endDate = Carbon::parse($this->input('end_date'), $timezone)->startOfDay();

        $summary = app(LeaveRequestService::class)->punchConflictSummary($employeeId, $startDate, $endDate);

        if ($summary['has_punches']) {
            $validator->errors()->add('acknowledge_punch_overlap', $summary['message']);
        }
    }
}
