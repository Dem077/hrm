<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends StoreDepartmentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');

        return [
            ...$this->departmentRules(),
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($department->id)],
        ];
    }
}
