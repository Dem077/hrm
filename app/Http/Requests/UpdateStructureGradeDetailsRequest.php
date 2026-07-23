<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStructureGradeDetailsRequest extends FormRequest
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
            'requirements' => ['required', 'string', 'max:50000', $this->nonEmptyHtmlRule()],
            'job_description' => ['nullable', 'string', 'max:100000'],
        ];
    }

    protected function nonEmptyHtmlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $text = trim(html_entity_decode(strip_tags((string) $value)));

            if ($text === '') {
                $fail('The requirements field is required.');
            }
        };
    }
}
