<?php

namespace App\Http\Requests;

use App\Enums\DutyType;
use App\Http\Requests\Concerns\NormalizesDutyTimes;
use App\Http\Requests\Concerns\ValidatesDutyRosterEmployeeAccess;
use App\Models\DutyShiftTemplate;
use App\Models\Employee;
use App\Services\Attendance\DutyRosterAssignmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkAssignDutyRosterRequest extends FormRequest
{
    use NormalizesDutyTimes;
    use ValidatesDutyRosterEmployeeAccess;

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
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'date_mode' => ['required', Rule::in(['range', 'specific'])],
            'from_date' => ['required_if:date_mode,range', 'nullable', 'date'],
            'to_date' => ['required_if:date_mode,range', 'nullable', 'date', 'after_or_equal:from_date'],
            'duty_dates' => ['nullable', 'array', 'max:'.DutyRosterAssignmentService::MAX_DAYS],
            'duty_dates.*' => ['date', 'distinct'],
            'duty_shift_template_id' => ['nullable', 'integer', 'exists:duty_shift_templates,id'],
            'duty_start_time' => ['required_without:duty_shift_template_id', 'nullable', 'date_format:H:i:s'],
            'duty_end_time' => ['required_without:duty_shift_template_id', 'nullable', 'date_format:H:i:s', 'after:duty_start_time'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'notes' => ['nullable', 'string', 'max:500'],
            'skip_weekends' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('date_mode') === 'range') {
                $from = $this->date('from_date');
                $to = $this->date('to_date');

                if ($from && $to && $from->diffInDays($to) > DutyRosterAssignmentService::MAX_DAYS) {
                    $validator->errors()->add(
                        'to_date',
                        'The date range may not exceed '.DutyRosterAssignmentService::MAX_DAYS.' days.',
                    );
                }
            }

            if ($this->input('date_mode') === 'specific' && $this->resolvedDates() === []) {
                $validator->errors()->add('duty_dates', 'Select at least one duty date.');
            }

            if ($this->input('date_mode') === 'range' && (! $this->date('from_date') || ! $this->date('to_date'))) {
                $validator->errors()->add('from_date', 'Select a valid date range.');
            }

            $invalidEmployees = Employee::query()
                ->whereIn('id', $this->input('employee_ids', []))
                ->where(function ($query) {
                    $query->where('duty_type', '!=', DutyType::Shift->value)
                        ->orWhere('is_active', false);
                })
                ->pluck('name');

            if ($invalidEmployees->isNotEmpty()) {
                $validator->errors()->add(
                    'employee_ids',
                    'Only active shift duty employees can be assigned: '.$invalidEmployees->join(', ').'.',
                );
            }

            $this->validateDutyRosterEmployeesAccess(
                $validator,
                array_map('intval', $this->input('employee_ids', [])),
            );

            $templateId = $this->integer('duty_shift_template_id');

            if ($templateId) {
                $template = DutyShiftTemplate::query()->find($templateId);

                if ($template && ! $template->is_active) {
                    $validator->errors()->add('duty_shift_template_id', 'The selected fixed duty is inactive.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'employee_ids' => array_values(array_unique(array_map('intval', (array) $this->input('employee_ids', [])))),
            'date_mode' => $this->input('date_mode', 'range'),
            'duty_dates' => array_values(array_filter((array) $this->input('duty_dates', []))),
            'grace_minutes' => $this->filled('grace_minutes') ? (int) $this->input('grace_minutes') : 15,
            'notes' => $this->input('notes') ?: null,
            'skip_weekends' => $this->boolean('skip_weekends'),
        ];

        $templateId = $this->integer('duty_shift_template_id');

        if ($templateId) {
            $template = DutyShiftTemplate::query()->find($templateId);

            if ($template) {
                $merge['duty_start_time'] = $template->duty_start_time;
                $merge['duty_end_time'] = $template->duty_end_time;
                $merge['grace_minutes'] = $template->grace_minutes;
            }
        } else {
            foreach (['duty_start_time', 'duty_end_time'] as $field) {
                if ($this->filled($field)) {
                    $merge[$field] = $this->normalizeTimeForStorage($this->input($field));
                }
            }
        }

        $this->merge($merge);
    }

    /**
     * @return list<string>
     */
    public function resolvedDates(): array
    {
        $service = app(DutyRosterAssignmentService::class);

        if ($this->input('date_mode') === 'specific') {
            return $service->normalizeDates(array_map('strval', $this->input('duty_dates', [])));
        }

        $from = $this->date('from_date');
        $to = $this->date('to_date');

        if (! $from || ! $to) {
            return [];
        }

        return $service->expandDateRange($from, $to, $this->boolean('skip_weekends'));
    }

    /**
     * @return array{start: string, end: string, grace: int}
     */
    public function dutyTimes(): array
    {
        return [
            'start' => (string) $this->input('duty_start_time'),
            'end' => (string) $this->input('duty_end_time'),
            'grace' => (int) $this->input('grace_minutes', 15),
        ];
    }
}
