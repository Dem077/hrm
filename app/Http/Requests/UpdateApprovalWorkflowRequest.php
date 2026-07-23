<?php

namespace App\Http\Requests;

use App\Enums\ApprovalWorkflowKind;
use App\Enums\LeaveApprovalStepKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalWorkflowRequest extends FormRequest
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
        $keys = array_map(
            fn (LeaveApprovalStepKey $key) => $key->value,
            LeaveApprovalStepKey::configurableKeys(),
        );

        return [
            'kind' => ['required', 'string', Rule::enum(ApprovalWorkflowKind::class)],
            'structure_node_id' => [
                'nullable',
                'integer',
                Rule::exists('structure_nodes', 'id')->where(fn ($query) => $query->whereNull('parent_id')),
            ],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.key' => ['required', 'string', Rule::in($keys)],
            'steps.*.enabled' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $steps = $this->input('steps', []);

        if (! is_array($steps)) {
            return;
        }

        $this->merge([
            'steps' => collect($steps)->map(fn ($step) => [
                'key' => is_array($step) ? ($step['key'] ?? null) : null,
                'enabled' => filter_var(is_array($step) ? ($step['enabled'] ?? false) : false, FILTER_VALIDATE_BOOLEAN),
            ])->all(),
        ]);
    }
}
