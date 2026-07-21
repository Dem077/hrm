<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReopenPayrollRunRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
