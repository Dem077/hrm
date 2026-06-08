<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicHolidayRequest extends FormRequest
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
        return $this->holidayRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->input('notes') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function holidayRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date', 'unique:public_holidays,date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
