<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Support\DateFormatter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveBalanceExportService
{
    public function __construct(
        private readonly LeaveRequestService $leaveRequestService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildRows(
        int $leaveYearOffset = 0,
        ?int $departmentId = null,
        ?int $employeeId = null,
        ?int $leaveTypeId = null,
    ): array {
        $employees = Employee::query()
            ->with(['grade.level.group', 'grade.level.node.group'])
            ->where('is_active', true)
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->whereHas('grade.level', fn ($levelQuery) => $levelQuery->where('structure_node_id', $departmentId));
            })
            ->when($employeeId, fn ($query) => $query->whereKey($employeeId))
            ->orderBy('name')
            ->get(['id', 'name', 'staff_id', 'joined_date', 'grade_id']);

        $rows = [];

        foreach ($employees as $employee) {
            $report = $this->leaveRequestService->buildEmployeeLeaveBalance(
                $employee,
                $leaveYearOffset,
                $leaveTypeId,
            );

            foreach ($report['employee']['balances'] as $balance) {
                $rows[] = [
                    'staff_id' => $report['employee']['staff_id'],
                    'employee_name' => $report['employee']['name'],
                    'department' => $report['employee']['department'] ?? '—',
                    'joined_date' => DateFormatter::formatDate($report['employee']['joined_date']),
                    'leave_type' => $balance['name'],
                    'leave_type_code' => $balance['code'] ?? '—',
                    'leave_year' => $report['selectedLeaveYear']['label'],
                    'leave_year_start' => DateFormatter::formatDate($balance['period_start']),
                    'leave_year_end' => DateFormatter::formatDate($balance['period_end']),
                    'annual_limit' => $balance['annual_limit'] ?? 'Unlimited',
                    'used_days' => $balance['used_days'] ?? '—',
                    'remaining_days' => $balance['remaining_days'] ?? '—',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<list<string|int>>
     */
    public function buildAllEmployeesUsedRows(?int $departmentId = null): array
    {
        $leaveTypes = LeaveType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $employees = Employee::query()
            ->with(['grade.level.group', 'grade.level.node.group'])
            ->where('is_active', true)
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->whereHas('grade.level', fn ($levelQuery) => $levelQuery->where('structure_node_id', $departmentId));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'staff_id', 'joined_date', 'grade_id']);

        $rows = [];

        foreach ($employees as $employee) {
            [$periodStart, $periodEnd] = $this->leaveRequestService->leaveYearBoundsByOffset($employee, 0);
            $path = $employee->grade?->resolvePath();

            $row = [
                $employee->staff_id,
                $employee->name,
                $path['node']['name'] ?? $path['group']['name'] ?? '—',
                DateFormatter::formatDateRange($periodStart, $periodEnd),
            ];

            foreach ($leaveTypes as $leaveType) {
                $row[] = $this->leaveRequestService->usedLeaveDaysInAnniversaryYear(
                    $employee->id,
                    $leaveType->id,
                    $periodStart,
                    $periodEnd,
                );
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function download(
        int $leaveYearOffset = 0,
        ?int $departmentId = null,
        ?int $employeeId = null,
        ?int $leaveTypeId = null,
    ): StreamedResponse {
        $rows = $this->buildRows($leaveYearOffset, $departmentId, $employeeId, $leaveTypeId);

        $headers = [
            'Staff ID',
            'Employee',
            'Department',
            'Joined',
            'Leave Type',
            'Code',
            'Leave Year',
            'Year From',
            'Year To',
            'Annual Limit',
            'Used',
            'Remaining',
        ];

        $sheetRows = array_map(fn (array $row) => array_values($row), $rows);

        return $this->streamSpreadsheet(
            'Leave Balances',
            $headers,
            $sheetRows,
            'leave-balances-employee',
        );
    }

    public function downloadAllEmployeesUsed(?int $departmentId = null): StreamedResponse
    {
        $leaveTypes = LeaveType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $headers = [
            'Staff ID',
            'Employee',
            'Department',
            'Leave Year',
            ...$leaveTypes->map(fn (LeaveType $leaveType) => $leaveType->name.' (Used)')->all(),
        ];

        return $this->streamSpreadsheet(
            'All Employees Used Leave',
            $headers,
            $this->buildAllEmployeesUsedRows($departmentId),
            'leave-balances-all-employees',
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int>>  $rows
     */
    private function streamSpreadsheet(
        string $sheetTitle,
        array $headers,
        array $rows,
        string $filenamePrefix,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);
        $sheet->fromArray($headers, null, 'A1');

        $rowIndex = 2;

        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $lastColumn = $this->columnLetter(count($headers));
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = $filenamePrefix.'-'.now(config('app.timezone', 'UTC'))->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function columnLetter(int $columnNumber): string
    {
        $letter = '';

        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)).$letter;
            $columnNumber = intdiv($columnNumber, 26);
        }

        return $letter;
    }
}
