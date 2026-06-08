<?php

namespace App\Http\Requests;

use App\Models\PublicHoliday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicHolidayRequest extends FormRequest
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
        /** @var PublicHoliday $holiday */
        $holiday = $this->route('public_holiday');

        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => [
                'required',
                'date',
                Rule::unique('public_holidays', 'date')->ignore($holiday->id),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
