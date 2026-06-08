<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceDutyPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->policyRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'duty_start_time' => $this->normalizeTimeForValidation($this->input('duty_start_time')),
            'duty_end_time' => $this->normalizeTimeForValidation($this->input('duty_end_time')),
            'saturday_duty_start_time' => $this->normalizeTimeForValidation($this->input('saturday_duty_start_time')),
            'saturday_duty_end_time' => $this->normalizeTimeForValidation($this->input('saturday_duty_end_time')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function policyRules(): array
    {
        return [
            'effective_from' => ['required', 'date', 'unique:attendance_duty_policies,effective_from'],
            'duty_start_time' => ['required', 'date_format:H:i'],
            'duty_end_time' => ['required', 'date_format:H:i', 'after:duty_start_time'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'saturday_duty_start_time' => ['required', 'date_format:H:i'],
            'saturday_duty_end_time' => ['required', 'date_format:H:i', 'after:saturday_duty_start_time'],
            'saturday_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
        ];
    }

    protected function normalizeTimeForValidation(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return substr($value, 0, 5);
    }
}
