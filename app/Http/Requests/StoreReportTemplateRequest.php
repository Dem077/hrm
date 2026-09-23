<?php

namespace App\Http\Requests;

use App\Support\ReportFieldCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportTemplateRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:report_templates,code'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_global' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'model_type' => ['required', 'string', Rule::in(array_keys(ReportFieldCatalog::MODELS))],
            'field_configs' => ['required', 'array', 'min:1'],
            'field_configs.*.field' => ['required', 'string', 'max:255'],
            'field_configs.*.heading' => ['nullable', 'string', 'max:255'],
            'field_configs.*.filter_type' => ['nullable', 'string', 'max:50'],
            'field_configs.*.filter_value' => ['nullable'],
            'field_configs.*.filter_value_from' => ['nullable'],
            'field_configs.*.filter_value_to' => ['nullable'],
            'field_configs.*.filter_values' => ['nullable', 'array'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_global' => $this->boolean('is_global', false),
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => $this->filled('sort_order') ? $this->integer('sort_order') : 0,
            'code' => $this->input('code') ?: null,
            'description' => $this->input('description') ?: null,
            'from_date' => $this->input('from_date') ?: null,
            'to_date' => $this->input('to_date') ?: null,
        ]);
    }

    /**
     * @return array{name: string, code: ?string, description: ?string, is_global: bool, is_active: bool, sort_order: int, definition: array<string, mixed>}
     */
    public function templateAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_global' => $validated['is_global'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'],
            'definition' => [
                'model_type' => $validated['model_type'],
                'field_configs' => $validated['field_configs'],
                'from_date' => $validated['from_date'] ?? null,
                'to_date' => $validated['to_date'] ?? null,
            ],
        ];
    }
}
