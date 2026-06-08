<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveTypeRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:50', 'unique:leave_types,code'],
            'description' => ['nullable', 'string', 'max:2000'],
            'requires_document' => ['boolean'],
            'is_visible_to_employees' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_document' => $this->boolean('requires_document'),
            'is_visible_to_employees' => $this->boolean('is_visible_to_employees', true),
            'is_active' => $this->boolean('is_active', true),
            'code' => $this->input('code') ?: null,
        ]);
    }
}
