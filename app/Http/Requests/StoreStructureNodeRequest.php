<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStructureNodeRequest extends FormRequest
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
        return $this->structureNodeRules();
    }

    protected function prepareForValidation(): void
    {
        $headGradeIds = $this->input('head_grade_ids', []);

        if (! is_array($headGradeIds)) {
            $headGradeIds = $headGradeIds !== null && $headGradeIds !== '' ? [$headGradeIds] : [];
        }

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'code' => $this->input('code') ?: null,
            'description' => $this->input('description') ?: null,
            'parent_id' => $this->input('parent_id') ?: null,
            'leave_approval_template_id' => $this->input('leave_approval_template_id') ?: null,
            'overtime_approval_template_id' => $this->input('overtime_approval_template_id') ?: null,
            'head_grade_ids' => array_values(array_filter(
                array_map('intval', $headGradeIds),
                fn (int $id) => $id > 0,
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function structureNodeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:structure_nodes,id'],
            'head_grade_ids' => ['nullable', 'array'],
            'head_grade_ids.*' => ['integer', 'exists:structure_grades,id'],
            'leave_approval_template_id' => [
                'nullable',
                'integer',
                Rule::exists('approval_templates', 'id')->where('kind', 'leave'),
            ],
            'overtime_approval_template_id' => [
                'nullable',
                'integer',
                Rule::exists('approval_templates', 'id')->where('kind', 'overtime'),
            ],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
