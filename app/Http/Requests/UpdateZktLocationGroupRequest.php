<?php

namespace App\Http\Requests;

use App\Models\ZktLocationGroup;
use Illuminate\Validation\Rule;

class UpdateZktLocationGroupRequest extends StoreZktLocationGroupRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ZktLocationGroup $group */
        $group = $this->route('zkt_location_group');

        return [
            ...parent::rules(),
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('zkt_location_groups', 'code')->ignore($group->id),
            ],
        ];
    }
}
