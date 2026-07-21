<?php

namespace App\Http\Requests;

use App\Models\ZktDevice;
use Illuminate\Contracts\Validation\Validator;

class UpdateZktDeviceRequest extends StoreZktDeviceRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $device = $this->route('zkt_device');

            if (! $device instanceof ZktDevice) {
                return;
            }

            if ($this->input('machine_type') === $device->machine_type->value) {
                return;
            }

            $reason = $device->machineTypeChangeBlockedReason();

            if ($reason !== null) {
                $validator->errors()->add('machine_type', $reason);
            }
        });
    }
}
