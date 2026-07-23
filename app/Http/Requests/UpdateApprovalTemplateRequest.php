<?php

namespace App\Http\Requests;

class UpdateApprovalTemplateRequest extends StoreApprovalTemplateRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['kind']);

        return $rules;
    }
}
