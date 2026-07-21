<?php

namespace App\Http\Requests;

class UpdateStructureLevelRequest extends StoreStructureLevelRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->structureLevelRules();
    }
}
