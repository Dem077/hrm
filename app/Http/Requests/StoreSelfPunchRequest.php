<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSelfPunchRequest extends FormRequest
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
            'self_punch_site_id' => ['required', 'integer', 'exists:self_punch_sites,id'],
            'punch_state' => ['required', 'integer', 'in:0,1'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'public_ip' => ['nullable', 'ip'],
            'device_id' => ['required', 'uuid'],
        ];
    }
}
