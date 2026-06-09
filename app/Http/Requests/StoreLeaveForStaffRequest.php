<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesLeaveAnnualLimit;
use App\Http\Requests\Concerns\ValidatesLeaveDateOverlap;
use App\Http\Requests\Concerns\ValidatesLeavePunchOverlap;
use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveForStaffRequest extends FormRequest
{
    use ValidatesLeaveAnnualLimit;
    use ValidatesLeaveDateOverlap;
    use ValidatesLeavePunchOverlap;

    public function authorize(): bool
    {
        return $this->user()?->can('leave-requests.record-for-others') ?? false;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('employee_id')) {
                return;
            }

            $this->validateLeaveDateOverlap($validator, $this->integer('employee_id'));
            $this->validateLeaveAnnualLimit($validator, $this->integer('employee_id'));
            $this->validateLeavePunchOverlap($validator, $this->integer('employee_id'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $leaveType = LeaveType::query()->find($this->integer('leave_type_id'));

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('is_active', true),
            ],
            'leave_type_id' => [
                'required',
                'integer',
                Rule::exists('leave_types', 'id')->where('is_active', true),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
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
