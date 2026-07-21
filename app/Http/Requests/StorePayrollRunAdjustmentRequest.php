<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollRunAdjustmentRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'title' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
