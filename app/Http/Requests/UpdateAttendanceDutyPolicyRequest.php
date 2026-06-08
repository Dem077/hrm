<?php

namespace App\Http\Requests;

use App\Models\AttendanceDutyPolicy;
use Illuminate\Validation\Rule;

class UpdateAttendanceDutyPolicyRequest extends StoreAttendanceDutyPolicyRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AttendanceDutyPolicy $policy */
        $policy = $this->route('attendance_duty_policy');

        return [
            'effective_from' => [
                'required',
                'date',
                Rule::unique('attendance_duty_policies', 'effective_from')->ignore($policy->id),
            ],
            'duty_start_time' => ['required', 'date_format:H:i'],
            'duty_end_time' => ['required', 'date_format:H:i', 'after:duty_start_time'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'saturday_duty_start_time' => ['required', 'date_format:H:i'],
            'saturday_duty_end_time' => ['required', 'date_format:H:i', 'after:saturday_duty_start_time'],
            'saturday_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
        ];
    }
}
