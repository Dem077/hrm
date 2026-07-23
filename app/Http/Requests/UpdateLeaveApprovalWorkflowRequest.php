<?php

namespace App\Http\Requests;

use App\Enums\LeaveApprovalStepKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveApprovalWorkflowRequest extends FormRequest
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
        $keys = array_map(
            fn (LeaveApprovalStepKey $key) => $key->value,
            LeaveApprovalStepKey::configurableKeys(),
        );

        return [
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
