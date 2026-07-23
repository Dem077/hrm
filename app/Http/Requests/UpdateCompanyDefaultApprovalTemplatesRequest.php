<?php

namespace App\Http\Requests;

use App\Enums\ApprovalWorkflowKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyDefaultApprovalTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance-settings.leave-workflow.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'leave_approval_template_id' => [
                'nullable',
                'integer',
                Rule::exists('approval_templates', 'id')->where('kind', ApprovalWorkflowKind::Leave->value),
            ],
            'overtime_approval_template_id' => [
                'nullable',
                'integer',
                Rule::exists('approval_templates', 'id')->where('kind', ApprovalWorkflowKind::Overtime->value),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'leave_approval_template_id' => $this->input('leave_approval_template_id') ?: null,
            'overtime_approval_template_id' => $this->input('overtime_approval_template_id') ?: null,
        ]);
    }
}
