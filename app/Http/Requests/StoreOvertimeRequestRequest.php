<?php

namespace App\Http\Requests;

use App\Services\Overtime\OvertimeRequestService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreOvertimeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('overtime-requests.create') ?? false;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employee = $this->user()?->employee;

            if (! $employee || $validator->errors()->isNotEmpty()) {
                return;
            }

            $timezone = config('app.timezone', 'UTC');
            $date = Carbon::parse((string) $this->input('overtime_date'), $timezone)->startOfDay();
            $eligibility = app(OvertimeRequestService::class)->eligibilityForDate($employee, $date);

            if ($eligibility === null) {
                $validator->errors()->add(
                    'overtime_date',
                    'No claimable overtime hours after duty end were found for this date.',
                );

                return;
            }

            $hours = (float) $this->input('hours');
            $available = (float) $eligibility['available_hours'];

            if ($hours > $available + 0.001) {
                $validator->errors()->add(
                    'hours',
                    'You can only claim up to '.$available.' overtime hours for this date.',
                );
            }

            // Force times to the attendance window so clients cannot invent OT times.
            $this->merge([
                'start_time' => $eligibility['start_time'],
                'end_time' => $eligibility['end_time'],
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overtime_date' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hours.min' => 'Overtime must be at least 0.25 hours (15 minutes).',
            'overtime_date.before_or_equal' => 'You can only apply for overtime on dates you have already worked.',
        ];
    }
}
