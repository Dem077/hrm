<?php

namespace App\Http\Requests;

use App\Models\AttendanceDutyPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceDutyPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_temporary')) {
                return;
            }

            if (! $this->filled('effective_from') || ! $this->filled('effective_until')) {
                return;
            }

            /** @var AttendanceDutyPolicy|null $existing */
            $existing = $this->route('attendance_duty_policy');

            if (AttendanceDutyPolicy::overlapsTemporaryPeriod(
                $this->string('effective_from')->toString(),
                $this->string('effective_until')->toString(),
                $existing?->id,
            )) {
                $validator->errors()->add('effective_until', 'This period overlaps an existing temporary duty policy.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->policyRules($this->route('attendance_duty_policy'));
    }

    protected function prepareForValidation(): void
    {
        $isTemporary = $this->boolean('is_temporary');

        $merge = [
            'is_temporary' => $isTemporary,
            'duty_start_time' => $this->normalizeTimeForValidation($this->input('duty_start_time')),
            'duty_end_time' => $this->normalizeTimeForValidation($this->input('duty_end_time')),
            'saturday_duty_start_time' => $this->normalizeTimeForValidation($this->input('saturday_duty_start_time')),
            'saturday_duty_end_time' => $this->normalizeTimeForValidation($this->input('saturday_duty_end_time')),
        ];

        if (! $isTemporary) {
            $merge['effective_until'] = null;
            $merge['name'] = null;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    protected function policyRules(?AttendanceDutyPolicy $existing = null): array
    {
        $isTemporary = $this->boolean('is_temporary');

        return [
            'is_temporary' => ['boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'effective_from' => [
                'required',
                'date',
                Rule::when(
                    ! $isTemporary,
                    Rule::unique('attendance_duty_policies', 'effective_from')
                        ->whereNull('effective_until')
                        ->ignore($existing?->id),
                ),
            ],
            'effective_until' => [
                Rule::requiredIf($isTemporary),
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],
            'duty_start_time' => ['required', 'date_format:H:i'],
            'duty_end_time' => ['required', 'date_format:H:i', 'after:duty_start_time'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'saturday_duty_start_time' => ['required', 'date_format:H:i'],
            'saturday_duty_end_time' => ['required', 'date_format:H:i', 'after:saturday_duty_start_time'],
            'saturday_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
        ];
    }

    protected function normalizeTimeForValidation(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return substr($value, 0, 5);
    }
}
