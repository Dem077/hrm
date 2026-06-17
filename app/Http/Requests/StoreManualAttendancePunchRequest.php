<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualAttendancePunchRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'duty_date' => ['required', 'date'],
            'punched_at' => ['required', 'date_format:H:i:s'],
            'punch_state' => ['required', 'integer', Rule::in([0, 1])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('punched_at')) {
            $time = $this->input('punched_at');

            $this->merge([
                'punched_at' => strlen((string) $time) === 5 ? "{$time}:00" : substr((string) $time, 0, 8),
            ]);
        }
    }

    public function employee(): Employee
    {
        return Employee::query()->findOrFail($this->integer('employee_id'));
    }

    public function punchedAt(string $timezone): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse(
            $this->string('duty_date')->toString().' '.$this->string('punched_at')->toString(),
            $timezone,
        );
    }
}
