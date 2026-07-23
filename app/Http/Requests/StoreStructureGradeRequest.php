<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStructureGradeRequest extends FormRequest
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
        return $this->structureGradeRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function structureGradeRules(): array
    {
        return [
            'grade' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'requirements' => ['required', 'string', 'max:50000', $this->nonEmptyHtmlRule('requirements')],
            'job_description' => ['nullable', 'string', 'max:100000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function nonEmptyHtmlRule(string $attribute): \Closure
    {
        return function (string $attr, mixed $value, \Closure $fail) use ($attribute): void {
            $text = trim(html_entity_decode(strip_tags((string) $value)));

            if ($text === '') {
                $fail("The {$attribute} field is required.");
            }
        };
    }
}
