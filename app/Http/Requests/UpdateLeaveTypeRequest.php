<?php

namespace App\Http\Requests;

use App\Models\LeaveType;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends StoreLeaveTypeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var LeaveType $leaveType */
        $leaveType = $this->route('leave_type');

        return [
            ...parent::rules(),
            'code' => ['nullable', 'string', 'max:50', Rule::unique('leave_types', 'code')->ignore($leaveType->id)],
        ];
    }
}
