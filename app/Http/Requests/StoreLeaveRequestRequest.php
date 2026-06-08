<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesLeaveDateOverlap;
use App\Http\Requests\Concerns\ValidatesLeavePunchOverlap;
use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveRequestRequest extends FormRequest
{
    use ValidatesLeaveDateOverlap;
    use ValidatesLeavePunchOverlap;

    public function authorize(): bool
    {
        return $this->user()?->can('leave-requests.create') ?? false;
    }

    public function withValidator(Validator $validator): void
    {
        $employeeId = $this->user()?->employee?->id;

        if (! $employeeId) {
            return;
        }

        $validator->after(function (Validator $validator) use ($employeeId): void {
            $this->validateLeaveDateOverlap($validator, $employeeId);
            $this->validateLeavePunchOverlap($validator, $employeeId);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $leaveType = LeaveType::query()->find($this->integer('leave_type_id'));

        return [
            'leave_type_id' => [
                'required',
                'integer',
                Rule::exists('leave_types', 'id')->where(function ($query): void {
                    $query->where('is_active', true)->where('is_visible_to_employees', true);
                }),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'document' => [
                Rule::requiredIf($leaveType?->requires_document ?? false),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
                'max:5120',
            ],
            'acknowledge_punch_overlap' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document.required' => 'A supporting document is required for this leave type.',
        ];
    }
}
