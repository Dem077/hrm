<?php

namespace App\Http\Requests;

use App\Enums\ZktConnectionProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProbeZktDeviceRequest extends FormRequest
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
            'ip_address' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['required', Rule::enum(ZktConnectionProtocol::class)],
            'comm_password' => ['nullable', 'integer', 'min:0'],
            'tcpmux_enabled' => ['boolean'],
            'tcpmux_subdomain' => ['nullable', 'string', 'max:255'],
            'tcpmux_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tcpmux_enabled' => $this->boolean('tcpmux_enabled'),
        ]);
    }
}
