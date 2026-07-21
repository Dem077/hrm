<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenRemoteDoorRequest extends FormRequest
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
            'remote_door_site_id' => ['required', 'integer', 'exists:remote_door_sites,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'public_ip' => ['nullable', 'string', 'max:45'],
        ];
    }
}
