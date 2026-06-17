<?php

namespace App\Http\Requests;

use App\Models\AppSetting;
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
            'annual_limit' => ['nullable', 'integer', 'min:0', 'max:366'],
            'can_carry_forward' => ['boolean'],
            'max_carry_forward_days' => ['nullable', 'integer', 'min:0', 'max:3660'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_document' => $this->boolean('requires_document'),
            'is_visible_to_employees' => $this->boolean('is_visible_to_employees', true),
            'is_active' => $this->boolean('is_active', true),
            'can_carry_forward' => AppSetting::current()->leave_carry_forward_enabled
                ? $this->boolean('can_carry_forward', false)
                : false,
            'code' => $this->input('code') ?: null,
            'annual_limit' => $this->filled('annual_limit') ? $this->integer('annual_limit') : null,
            'max_carry_forward_days' => AppSetting::current()->leave_carry_forward_enabled
                && $this->boolean('can_carry_forward')
                && $this->filled('max_carry_forward_days')
                ? $this->integer('max_carry_forward_days')
                : null,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! AppSetting::current()->leave_carry_forward_enabled) {
                return;
            }

            if (! $this->boolean('can_carry_forward')) {
                return;
            }

            if (! $this->filled('annual_limit')) {
                $validator->errors()->add('can_carry_forward', 'Carry forward requires an annual limit.');
            }

            if (! $this->filled('max_carry_forward_days')) {
                $validator->errors()->add('max_carry_forward_days', 'Set max accumulated carry forward days.');
            }
        });
    }
}
