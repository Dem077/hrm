<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveCarryForwardRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'from_leave_year_offset' => ['required', 'integer', 'min:0'],
            'to_leave_year_offset' => ['required', 'integer', 'min:0'],
            'days' => ['required', 'integer', 'min:1', 'max:3660'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
