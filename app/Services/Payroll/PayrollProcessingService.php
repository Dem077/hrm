<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceDayStatus;
use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Services\Attendance\AttendanceSheetService;
use App\Services\Attendance\PayrollPeriodService;
use App\Services\Overtime\OvertimeRequestService;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollProcessingService
{
    public function __construct(
        private readonly AttendanceSheetService $attendanceSheetService,
        private readonly PayrollPeriodService $payrollPeriodService,
        private readonly PayrollFormulaEvaluator $formulaEvaluator,
        private readonly OvertimeRequestService $overtimeRequestService,
    ) {}

    /**
     * @return array{period: array<string, string>, rows: list<array<string, mixed>>}
     */
    public function build(int $periodOffset = 0, ?int $departmentId = null): array
    {
        $period = $this->payrollPeriodService->recentPeriodByOffset($periodOffset)
            ?? $this->payrollPeriodService->recentPeriodByOffset(0);

        return $this->buildForDateRange(
            $period['from'],
            $period['to'],
            $period['label'],
            $departmentId,
        );
    }

    /**
     * @return array{period: array<string, string>, rows: list<array<string, mixed>>}
     */
    public function buildForDateRange(
        CarbonInterface $from,
        CarbonInterface $to,
        string $label,
        ?int $departmentId = null,
    ): array {
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $employees = Employee::query()
            ->with([
                'grade.level.group',
                'grade.level.node.group',
                'grade.level.node.parent',
                'grade.payrollComponents' => fn ($query) => $query->where('is_active', true),
            ])
            ->where('is_active', true)
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->whereHas('grade.level', fn ($levelQuery) => $levelQuery->where('structure_node_id', $departmentId));
            })
            ->orderBy('name')
            ->get([
                'id',
                'staff_id',
                'name',
                'national_id',
                'grade_id',
                'bank_name',
                'account_name',
                'account_no',
            ]);

        $rows = [];

            foreach ($employees as $employee) {
            $attendance = $this->attendanceSheetService->build($from, $to, null, $employee->id);
            $attendanceRows = collect($attendance['rows']);
            $summary = $this->attendanceSheetService->summarizeRows($attendance['rows']);
            $daysAttended = $attendanceRows
                ->whereIn('status', [
                    AttendanceDayStatus::Present->value,
                    AttendanceDayStatus::Late->value,
                    AttendanceDayStatus::Incomplete->value,
                ])
                ->count();
            $hoursWorked = round(
                $attendanceRows->sum(fn (array $row) => (float) ($row['working_minutes'] ?? 0)) / 60,
                2,
            );
            $lateMinutes = (int) ($summary['late_minutes'] ?? 0);
            $absentDays = (int) ($summary['absent_days'] ?? 0);
            $presentDays = (int) ($summary['present_days'] ?? $daysAttended);
            $overtimeHours = $this->overtimeRequestService->approvedHoursInPeriod($employee->id, $from, $to);

            $components = $employee->grade?->payrollComponents ?? collect();
            $basicSalary = (float) ($components
                ->firstWhere('code', PayrollComponent::BASIC_SALARY_CODE)
                ?->pivot
                ?->amount ?? 0);

            $formulaVariables = $this->buildFormulaVariables(
                $from,
                $to,
                $attendanceRows->all(),
                $basicSalary,
                $overtimeHours,
            );

            $gross = 0.0;
            $deductions = 0.0;
            $details = [];

            foreach ($components as $component) {
                $rate = $component->usesGlobalRate()
                    ? (float) ($component->global_rate ?? 0)
                    : (float) ($component->pivot->amount ?? 0);

                $amount = match ($component->calculation_method) {
                    PayrollComponentCalculationMethod::Daily => round($rate * $daysAttended, 2),
                    PayrollComponentCalculationMethod::Hourly => round($rate * $hoursWorked, 2),
                    PayrollComponentCalculationMethod::PerLateMinute => round($rate * $lateMinutes, 2),
                    PayrollComponentCalculationMethod::PerLateMinuteOfBasic => round(
                        ($basicSalary * ($rate / 100)) * $lateMinutes,
                        2,
                    ),
                    PayrollComponentCalculationMethod::PerAbsentDay => round($rate * $absentDays, 2),
                    PayrollComponentCalculationMethod::PerAbsentDayOfBasic => round(
                        ($basicSalary * ($rate / 100)) * $absentDays,
                        2,
                    ),
                    PayrollComponentCalculationMethod::PerOvertimeHour => round($rate * $overtimeHours, 2),
                    PayrollComponentCalculationMethod::PerOvertimeHourOfBasic => round(
                        ($basicSalary * ($rate / 100)) * $overtimeHours,
                        2,
                    ),
                    PayrollComponentCalculationMethod::CustomFormula => $this->safeEvaluateFormula(
                        (string) ($component->calculation_formula ?? ''),
                        $formulaVariables,
                    ),
                    default => $rate,
                };

                if ($component->type === PayrollComponentType::Addition) {
                    $gross += $amount;
                } else {
                    $deductions += $amount;
                }

                $details[] = [
                    'component' => $component->name,
                    'method' => $component->calculation_method->value,
                    'method_label' => $component->calculation_method->label(),
                    'rate' => $rate,
                    'amount' => $amount,
                    'type' => $component->type->value,
                    'basic_salary' => $component->calculation_method->isPercentageOfBasicSalary()
                        || $component->calculation_method->isCustomFormula()
                        ? $basicSalary
                        : null,
                    'formula' => $component->calculation_method->isCustomFormula()
                        ? $component->calculation_formula
                        : null,
                    'formula_variables' => $component->calculation_method->isCustomFormula()
                        ? $formulaVariables
                        : null,
                    'calculation_inputs' => $this->calculationInputs(
                        $component->calculation_method,
                        $rate,
                        $daysAttended,
                        $hoursWorked,
                        $lateMinutes,
                        $absentDays,
                        $overtimeHours,
                        $basicSalary,
                        $formulaVariables,
                        $component->calculation_formula,
                    ),
                    'calculation_summary' => $this->calculationSummary(
                        $component->calculation_method,
                        $rate,
                        $amount,
                        $daysAttended,
                        $hoursWorked,
                        $lateMinutes,
                        $absentDays,
                        $overtimeHours,
                        $basicSalary,
                        $component->calculation_formula,
                    ),
                ];
            }

            $path = $employee->grade?->resolvePath();

            $rows[] = [
                'employee_id' => $employee->id,
                'staff_id' => $employee->staff_id,
                'employee_name' => $employee->name,
                'national_id' => $employee->national_id,
                'department' => $path['node']['name'] ?? $path['group']['name'] ?? null,
                'department_id' => $path['node']['id'] ?? null,
                'designation' => $employee->grade?->label(),
                'bank_name' => $employee->bank_name,
                'account_name' => $employee->account_name,
                'account_no' => $employee->account_no,
                'days_attended' => $daysAttended,
                'hours_worked' => $hoursWorked,
                'late_minutes' => $lateMinutes,
                'absent_days' => $absentDays,
                'present_days' => (int) ($formulaVariables['present_days'] ?? $daysAttended),
                'gross' => round($gross, 2),
                'deductions' => round($deductions, 2),
                'net' => round($gross - $deductions, 2),
                'details' => $details,
                'formula_variables' => $formulaVariables,
            ];
        }

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $label,
            ],
            'rows' => $rows,
        ];
    }

    public function downloadExcel(int $periodOffset = 0, ?int $departmentId = null): StreamedResponse
    {
        $report = $this->build($periodOffset, $departmentId);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll');
        $sheet->fromArray([
            'Staff ID',
            'Employee',
            'Org unit',
            'Grade',
            'Days Attended',
            'Hours Worked',
            'Gross',
            'Deductions',
            'Net',
            'Details',
        ], null, 'A1');

        $rowNum = 2;
        foreach ($report['rows'] as $row) {
            $detailText = collect($row['details'])
                ->map(fn (array $item) => "{$item['component']} ({$item['method']}) = {$item['amount']}")
                ->implode('; ');

            $sheet->fromArray([
                $row['staff_id'],
                $row['employee_name'],
                $row['department'] ?? '—',
                $row['designation'] ?? '—',
                $row['days_attended'],
                $row['hours_worked'],
                $row['gross'],
                $row['deductions'],
                $row['net'],
                $detailText,
            ], null, 'A'.$rowNum);
            $rowNum++;
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'payroll-'.now(config('app.timezone', 'UTC'))->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Actual figures used by custom payroll formulas for one employee/period.
     *
     * @param  list<array<string, mixed>>  $attendanceRows
     * @return array{
     *     absent_days: int,
     *     present_days: int,
     *     late_minutes: int,
     *     basic_salary: float,
     *     hours_worked: float,
     *     additional_hours_worked: float,
     *     overtime_hours: float,
     *     working_days: int,
     *     total_days_of_payroll: int
     * }
     */
    public function buildFormulaVariables(
        CarbonInterface $from,
        CarbonInterface $to,
        array $attendanceRows,
        float $basicSalary,
        float $overtimeHours = 0,
    ): array {
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $rows = collect($attendanceRows);
        $summary = $this->attendanceSheetService->summarizeRows($attendanceRows);

        $presentDays = (int) ($summary['present_days'] ?? 0);
        $absentDays = (int) ($summary['absent_days'] ?? 0);
        $lateMinutes = (int) ($summary['late_minutes'] ?? 0);

        $hoursWorked = round(
            $rows->sum(fn (array $row) => (float) ($row['working_minutes'] ?? 0)) / 60,
            2,
        );

        // Expected work days in the period (excludes holidays / weekends marked as holiday).
        $workingDays = $rows
            ->reject(fn (array $row) => ($row['status'] ?? null) === AttendanceDayStatus::Holiday->value)
            ->count();

        // Total calendar days covered by the payroll period (inclusive).
        $totalDaysOfPayroll = (int) $from->diffInDays($to) + 1;

        return [
            'absent_days' => $absentDays,
            'present_days' => $presentDays,
            'late_minutes' => $lateMinutes,
            'basic_salary' => round($basicSalary, 2),
            'hours_worked' => $hoursWorked,
            'additional_hours_worked' => $this->additionalHoursWorked($rows),
            'overtime_hours' => round($overtimeHours, 2),
            'working_days' => $workingDays,
            'total_days_of_payroll' => $totalDaysOfPayroll,
        ];
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    protected function safeEvaluateFormula(string $formula, array $variables): float
    {
        if (trim($formula) === '') {
            return 0.0;
        }

        try {
            return $this->formulaEvaluator->evaluate($formula, $variables);
        } catch (ValidationException) {
            return 0.0;
        }
    }

    /**
     * @param  array<string, float|int>  $formulaVariables
     * @return list<array{key: string, label: string, value: float|int|string}>
     */
    protected function calculationInputs(
        PayrollComponentCalculationMethod $method,
        float $rate,
        int $daysAttended,
        float $hoursWorked,
        int $lateMinutes,
        int $absentDays,
        float $overtimeHours,
        float $basicSalary,
        array $formulaVariables,
        ?string $formula = null,
    ): array {
        return match ($method) {
            PayrollComponentCalculationMethod::Fixed => [
                ['key' => 'rate', 'label' => 'Fixed amount', 'value' => $rate],
            ],
            PayrollComponentCalculationMethod::Daily => [
                ['key' => 'rate', 'label' => 'Rate / attended day', 'value' => $rate],
                ['key' => 'days_attended', 'label' => 'Days attended', 'value' => $daysAttended],
            ],
            PayrollComponentCalculationMethod::Hourly => [
                ['key' => 'rate', 'label' => 'Rate / hour', 'value' => $rate],
                ['key' => 'hours_worked', 'label' => 'Hours worked', 'value' => $hoursWorked],
            ],
            PayrollComponentCalculationMethod::PerLateMinute => [
                ['key' => 'rate', 'label' => 'Rate / late minute', 'value' => $rate],
                ['key' => 'late_minutes', 'label' => 'Late minutes', 'value' => $lateMinutes],
            ],
            PayrollComponentCalculationMethod::PerLateMinuteOfBasic => [
                ['key' => 'basic_salary', 'label' => 'Basic salary', 'value' => $basicSalary],
                ['key' => 'rate', 'label' => '% of basic / late minute', 'value' => $rate],
                ['key' => 'late_minutes', 'label' => 'Late minutes', 'value' => $lateMinutes],
            ],
            PayrollComponentCalculationMethod::PerAbsentDay => [
                ['key' => 'rate', 'label' => 'Rate / absent day', 'value' => $rate],
                ['key' => 'absent_days', 'label' => 'Absent days', 'value' => $absentDays],
            ],
            PayrollComponentCalculationMethod::PerAbsentDayOfBasic => [
                ['key' => 'basic_salary', 'label' => 'Basic salary', 'value' => $basicSalary],
                ['key' => 'rate', 'label' => '% of basic / absent day', 'value' => $rate],
                ['key' => 'absent_days', 'label' => 'Absent days', 'value' => $absentDays],
            ],
            PayrollComponentCalculationMethod::PerOvertimeHour => [
                ['key' => 'rate', 'label' => 'Rate / overtime hour', 'value' => $rate],
                ['key' => 'overtime_hours', 'label' => 'Approved overtime hours', 'value' => $overtimeHours],
            ],
            PayrollComponentCalculationMethod::PerOvertimeHourOfBasic => [
                ['key' => 'basic_salary', 'label' => 'Basic salary', 'value' => $basicSalary],
                ['key' => 'rate', 'label' => '% of basic / overtime hour', 'value' => $rate],
                ['key' => 'overtime_hours', 'label' => 'Approved overtime hours', 'value' => $overtimeHours],
            ],
            PayrollComponentCalculationMethod::CustomFormula => $this->customFormulaInputs(
                (string) $formula,
                $formulaVariables,
            ),
        };
    }

    /**
     * @param  array<string, float|int>  $formulaVariables
     * @return list<array{key: string, label: string, value: float|int|string}>
     */
    protected function customFormulaInputs(string $formula, array $formulaVariables): array
    {
        $entries = collect($formulaVariables)
            ->map(fn ($value, string $key) => [
                'key' => $key,
                'label' => match ($key) {
                    'absent_days' => 'Absent days',
                    'present_days' => 'Present days',
                    'late_minutes' => 'Late minutes',
                    'basic_salary' => 'Basic salary',
                    'hours_worked' => 'Hours worked',
                    'additional_hours_worked' => 'Additional hours worked',
                    'overtime_hours' => 'Approved overtime hours',
                    'working_days' => 'Number of working days',
                    'total_days_of_payroll' => 'Total days of payroll',
                    default => str_replace('_', ' ', $key),
                },
                'value' => $value,
            ]);

        if (trim($formula) !== '') {
            $entries = $entries->filter(
                fn (array $entry) => preg_match('/\b'.preg_quote($entry['key'], '/').'\b/', $formula) === 1,
            );
        }

        return $entries->values()->all();
    }

    protected function calculationSummary(
        PayrollComponentCalculationMethod $method,
        float $rate,
        float $amount,
        int $daysAttended,
        float $hoursWorked,
        int $lateMinutes,
        int $absentDays,
        float $overtimeHours,
        float $basicSalary,
        ?string $formula,
    ): string {
        $money = fn (float $value): string => number_format($value, 2, '.', ',');
        $qty = fn (float|int $value): string => is_int($value) || fmod((float) $value, 1.0) === 0.0
            ? number_format((float) $value, 0, '.', ',')
            : number_format((float) $value, 2, '.', ',');

        return match ($method) {
            PayrollComponentCalculationMethod::Fixed => "Fixed amount = {$money($amount)}",
            PayrollComponentCalculationMethod::Daily => "{$money($rate)} × {$qty($daysAttended)} days attended = {$money($amount)}",
            PayrollComponentCalculationMethod::Hourly => "{$money($rate)} × {$qty($hoursWorked)} hours worked = {$money($amount)}",
            PayrollComponentCalculationMethod::PerLateMinute => "{$money($rate)} × {$qty($lateMinutes)} late minutes = {$money($amount)}",
            PayrollComponentCalculationMethod::PerLateMinuteOfBasic => "({$money($basicSalary)} × {$qty($rate)}%) × {$qty($lateMinutes)} late minutes = {$money($amount)}",
            PayrollComponentCalculationMethod::PerAbsentDay => "{$money($rate)} × {$qty($absentDays)} absent days = {$money($amount)}",
            PayrollComponentCalculationMethod::PerAbsentDayOfBasic => "({$money($basicSalary)} × {$qty($rate)}%) × {$qty($absentDays)} absent days = {$money($amount)}",
            PayrollComponentCalculationMethod::PerOvertimeHour => "{$money($rate)} × {$qty($overtimeHours)} overtime hours = {$money($amount)}",
            PayrollComponentCalculationMethod::PerOvertimeHourOfBasic => "({$money($basicSalary)} × {$qty($rate)}%) × {$qty($overtimeHours)} overtime hours = {$money($amount)}",
            PayrollComponentCalculationMethod::CustomFormula => trim((string) $formula) !== ''
                ? "Formula ({$formula}) = {$money($amount)}"
                : "Custom formula = {$money($amount)}",
        };
    }

    /**
     * Hours worked beyond scheduled duty length for the period.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $attendanceRows
     */
    protected function additionalHoursWorked($attendanceRows): float
    {
        $extraMinutes = 0.0;

        foreach ($attendanceRows as $row) {
            $worked = (float) ($row['working_minutes'] ?? 0);
            if ($worked <= 0) {
                continue;
            }

            $scheduled = $this->scheduledDutyMinutes(
                (string) ($row['duty_start_time'] ?? ''),
                (string) ($row['duty_end_time'] ?? ''),
            );

            if ($scheduled === null) {
                continue;
            }

            $extraMinutes += max(0, $worked - $scheduled);
        }

        return round($extraMinutes / 60, 2);
    }

    protected function scheduledDutyMinutes(string $start, string $end): ?int
    {
        $start = trim($start);
        $end = trim($end);

        if ($start === '' || $end === '' || $start === '—' || $end === '—') {
            return null;
        }

        try {
            $from = \Illuminate\Support\Carbon::createFromFormat('H:i', substr($start, 0, 5));
            $to = \Illuminate\Support\Carbon::createFromFormat('H:i', substr($end, 0, 5));
        } catch (\Throwable) {
            return null;
        }

        if (! $from || ! $to) {
            return null;
        }

        if ($to->lte($from)) {
            $to->addDay();
        }

        return (int) $from->diffInMinutes($to);
    }
}
