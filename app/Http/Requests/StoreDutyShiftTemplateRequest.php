<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesDutyTimes;
use Illuminate\Foundation\Http\FormRequest;

class StoreDutyShiftTemplateRequest extends FormRequest
{
    use NormalizesDutyTimes;

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
            'name' => ['required', 'string', 'max:100'],
            'duty_start_time' => ['required', 'date_format:H:i:s'],
            'duty_end_time' => ['required', 'date_format:H:i:s', 'after:duty_start_time'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'grace_minutes' => $this->filled('grace_minutes') ? (int) $this->input('grace_minutes') : 15,
            'notes' => $this->input('notes') ?: null,
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
        ];

        foreach (['duty_start_time', 'duty_end_time'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = $this->normalizeTimeForStorage($this->input($field));
            }
        }

        $this->merge($merge);
    }
}
