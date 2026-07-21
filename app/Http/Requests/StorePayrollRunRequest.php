<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRunRequest extends FormRequest
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
            'period_source' => ['required', 'string', 'in:global,custom'],
            'from' => ['required_if:period_source,custom', 'nullable', 'date'],
            'to' => ['required_if:period_source,custom', 'nullable', 'date'],
        ];
    }
}
