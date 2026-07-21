<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesGradePayrollItems;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGradePayrollRequest extends FormRequest
{
    use ValidatesGradePayrollItems;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->gradePayrollItemRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateGradePayrollItems($validator);
    }
}
