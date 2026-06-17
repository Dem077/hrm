<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceDayStatus;
use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Models\Employee;
use App\Services\Attendance\AttendanceSheetService;
use App\Services\Attendance\PayrollPeriodService;
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

        $from = $period['from'];
        $to = $period['to'];

        $employees = Employee::query()
            ->with([
                'department:id,name',
                'designation:id,name',
                'designation.payrollComponents' => fn ($query) => $query->where('is_active', true),
            ])
            ->where('is_active', true)
            ->whereNotNull('designation_id')
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($employees as $employee) {
            $attendance = $this->attendanceSheetService->build($from, $to, null, $employee->id);
            $attendanceRows = collect($attendance['rows']);
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

            $gross = 0.0;
            $deductions = 0.0;
            $details = [];

            foreach ($employee->designation?->payrollComponents ?? [] as $component) {
                $rate = (float) ($component->pivot->amount ?? 0);
                $amount = match ($component->calculation_method) {
                    PayrollComponentCalculationMethod::Daily => round($rate * $daysAttended, 2),
                    PayrollComponentCalculationMethod::Hourly => round($rate * $hoursWorked, 2),
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
                ];
            }

            $rows[] = [
                'employee_id' => $employee->id,
                'staff_id' => $employee->staff_id,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'days_attended' => $daysAttended,
                'hours_worked' => $hoursWorked,
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
                'label' => $period['label'],
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
            'Department',
            'Designation',
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
