<?php

namespace App\Http\Requests;

use App\Enums\AttendanceMachineBrand;
use App\Enums\ZktConnectionMode;
use App\Enums\ZktConnectionProtocol;
use App\Enums\ZktConnectionStatus;
use App\Enums\ZktMachineType;
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
        $connectionMode = $this->input('connection_mode', ZktConnectionMode::TcpPull->value);

        $merge = [
            'connection_status' => ZktConnectionStatus::resolve($this->input('connection_status'))->value,
            'connection_mode' => $connectionMode,
            'tcpmux_enabled' => $this->boolean('tcpmux_enabled'),
            'is_active' => $this->boolean('is_active', true),
            'auto_sync' => $this->boolean('auto_sync', true),
            'serial_number' => $this->input('serial_number') ?: null,
        ];

        if ($connectionMode === ZktConnectionMode::AdmsPush->value) {
            $merge['ip_address'] = $this->input('ip_address') ?: '0.0.0.0';
            $merge['port'] = $this->input('port') ?: config('zkt.default_port', 4370);
            $merge['protocol'] = $this->input('protocol') ?: config('zkt.default_protocol', 'tcp');
            $merge['auto_sync'] = false;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    protected function deviceRules(): array
    {
        $deviceId = $this->route('zkt_device')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', Rule::enum(AttendanceMachineBrand::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'machine_type' => ['required', Rule::enum(ZktMachineType::class)],
            'connection_mode' => ['required', Rule::enum(ZktConnectionMode::class)],
            'ip_address' => [
                Rule::requiredIf(fn () => $this->input('connection_mode') === ZktConnectionMode::TcpPull->value),
                'nullable',
                'string',
                'max:255',
            ],
            'port' => [
                Rule::requiredIf(fn () => $this->input('connection_mode') === ZktConnectionMode::TcpPull->value),
                'nullable',
                'integer',
                'min:1',
                'max:65535',
            ],
            'protocol' => [
                Rule::requiredIf(fn () => $this->input('connection_mode') === ZktConnectionMode::TcpPull->value),
                'nullable',
                Rule::enum(ZktConnectionProtocol::class),
            ],
            'comm_password' => ['nullable', 'integer', 'min:0'],
            'serial_number' => [
                Rule::requiredIf(fn () => $this->input('connection_mode') === ZktConnectionMode::AdmsPush->value),
                'nullable',
                'string',
                'max:255',
                Rule::unique('zkt_devices', 'serial_number')->ignore($deviceId),
            ],
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
