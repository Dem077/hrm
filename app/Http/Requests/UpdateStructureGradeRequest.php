<?php

namespace App\Http\Requests;

class UpdateStructureGradeRequest extends StoreStructureGradeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->structureGradeRules();
    }
}
