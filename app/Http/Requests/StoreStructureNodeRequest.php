<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'code' => $this->input('code') ?: null,
            'description' => $this->input('description') ?: null,
            'parent_id' => $this->input('parent_id') ?: null,
            'head_employee_id' => $this->input('head_employee_id') ?: null,
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
            'head_employee_id' => ['nullable', 'exists:employees,id'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
