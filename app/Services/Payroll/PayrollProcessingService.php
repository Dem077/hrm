<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceDayStatus;
use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Services\Attendance\AttendanceSheetService;
use App\Services\Attendance\PayrollPeriodService;
use Carbon\CarbonInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollProcessingService
{
    public function __construct(
        private readonly AttendanceSheetService $attendanceSheetService,
        private readonly PayrollPeriodService $payrollPeriodService,
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
                $attendanceRows->sum(fn (array $row) => (int) ($row['working_minutes'] ?? 0)) / 60,
                2
            );
            $lateMinutes = (int) ($summary['late_minutes'] ?? 0);
            $absentDays = (int) ($summary['absent_days'] ?? 0);

            $gross = 0.0;
            $deductions = 0.0;
            $details = [];

            $components = $employee->grade?->payrollComponents ?? collect();
            $basicSalary = (float) ($components
                ->firstWhere('code', PayrollComponent::BASIC_SALARY_CODE)
                ?->pivot
                ?->amount ?? 0);

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
                    'rate' => $rate,
                    'amount' => $amount,
                    'type' => $component->type->value,
                    'basic_salary' => $component->calculation_method->isPercentageOfBasicSalary()
                        ? $basicSalary
                        : null,
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
                'gross' => round($gross, 2),
                'deductions' => round($deductions, 2),
                'net' => round($gross - $deductions, 2),
                'details' => $details,
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
}
