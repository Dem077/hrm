<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSelfPunchSiteRequest extends FormRequest
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
        $siteId = $this->route('self_punch_site')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('self_punch_sites', 'code')->ignore($siteId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:20', 'max:5000'],
            'max_accuracy_meters' => ['required', 'integer', 'min:10', 'max:1000'],
            'require_public_ip' => ['boolean'],
            'allowed_public_ips' => [
                'nullable',
                'array',
                Rule::requiredIf(fn () => $this->boolean('require_public_ip')),
                Rule::when($this->boolean('require_public_ip'), ['min:1']),
            ],
            'allowed_public_ips.*' => ['string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('allowed_public_ips_text') && ! $this->filled('allowed_public_ips')) {
            $this->merge([
                'allowed_public_ips' => $this->input('allowed_public_ips_text'),
            ]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'require_public_ip' => $this->boolean('require_public_ip', false),
            'code' => $this->filled('code') ? $this->input('code') : null,
            'description' => $this->filled('description') ? $this->input('description') : null,
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
            'allowed_public_ips' => $this->normalizeIpList($this->input('allowed_public_ips') ?? $this->input('allowed_public_ips_text')),
            'employee_ids' => collect($this->input('employee_ids', []))
                ->filter(fn ($id) => filled($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return list<string>
     */
    protected function normalizeIpList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
