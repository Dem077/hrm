<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateRemoteDoorSiteRequest extends StoreRemoteDoorSiteRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $siteId = $this->route('remote_door_site')?->id;

        return array_merge(parent::rules(), [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('remote_door_sites', 'code')->ignore($siteId)],
        ]);
    }
}
