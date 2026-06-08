<?php

namespace App\Http\Requests;

use App\Enums\AttendanceMachineBrand;
use App\Enums\ZktConnectionProtocol;
use App\Enums\ZktConnectionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZktDeviceRequest extends FormRequest
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
        return $this->deviceRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'connection_status' => ZktConnectionStatus::resolve($this->input('connection_status'))->value,
            'tcpmux_enabled' => $this->boolean('tcpmux_enabled'),
            'is_active' => $this->boolean('is_active', true),
            'auto_sync' => $this->boolean('auto_sync', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function deviceRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', Rule::enum(AttendanceMachineBrand::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'protocol' => ['required', Rule::enum(ZktConnectionProtocol::class)],
            'comm_password' => ['nullable', 'integer', 'min:0'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'model_name' => ['nullable', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'auto_sync' => ['boolean'],
            'sync_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'connection_status' => ['required', Rule::enum(ZktConnectionStatus::class)],
            'last_connected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'last_sync_error' => ['nullable', 'string'],
            'tcpmux_enabled' => ['boolean'],
            'tcpmux_subdomain' => ['nullable', 'required_if:tcpmux_enabled,true', 'string', 'max:255'],
            'tcpmux_port' => ['nullable', 'required_if:tcpmux_enabled,true', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
