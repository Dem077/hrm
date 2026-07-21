<?php

namespace App\Http\Requests;

class UpdateStructureNodeRequest extends StoreStructureNodeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->structureNodeRules();
    }
}
