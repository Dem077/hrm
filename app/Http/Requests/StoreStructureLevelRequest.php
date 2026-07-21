<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStructureLevelRequest extends FormRequest
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
        return $this->structureLevelRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'structure_group_id' => $this->input('structure_group_id') ?: null,
            'structure_node_id' => $this->input('structure_node_id') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function structureLevelRules(): array
    {
        return [
            'level_number' => ['required', 'integer', 'min:1'],
            'reference_title' => ['required', 'string', 'max:255'],
            'structure_group_id' => ['nullable', 'exists:structure_groups,id'],
            'structure_node_id' => ['nullable', 'exists:structure_nodes,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
