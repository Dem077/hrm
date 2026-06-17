<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveManualAttendancePunchRequest extends FormRequest
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
        return [
            'punch_log_ids' => ['required', 'array', 'min:1'],
            'punch_log_ids.*' => ['required', 'integer', 'exists:zkt_attendance_logs,id'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
