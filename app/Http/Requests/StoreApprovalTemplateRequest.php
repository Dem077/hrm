<?php

namespace App\Http\Requests;

use App\Enums\ApprovalWorkflowKind;
use App\Enums\LeaveApprovalStepKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.key' => ['required', 'string', Rule::in($keys)],
            'steps.*.enabled' => ['required', 'boolean'],
            'steps.*.head_grade_ids' => ['nullable', 'array'],
            'steps.*.head_grade_ids.*' => ['integer', 'exists:structure_grades,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $steps = $this->input('steps', []);

        if (! is_array($steps)) {
            return;
        }

        $this->merge([
            'description' => $this->input('description') ?: null,
            'steps' => collect($steps)->map(function ($step) {
                if (! is_array($step)) {
                    return [
                        'key' => null,
                        'enabled' => false,
                        'head_grade_ids' => [],
                    ];
                }

                $ids = $step['head_grade_ids'] ?? [];
                if (! is_array($ids)) {
                    $ids = [];
                }

                return [
                    'key' => $step['key'] ?? null,
                    'enabled' => filter_var($step['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'head_grade_ids' => array_values(array_unique(array_filter(
                        array_map('intval', $ids),
                        fn (int $id) => $id > 0,
                    ))),
                ];
            })->all(),
        ]);
    }
}
