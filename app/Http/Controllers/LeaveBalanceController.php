<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceExportService;
use App\Services\Leave\LeaveRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveBalanceController extends Controller
{
    public function index(Request $request, LeaveRequestService $leaveService): Response
    {
        $filters = $this->resolveFilters($request);

        $employee = $this->resolveEmployee($filters['departmentId'], $filters['employeeId']);

        $report = $employee
            ? $leaveService->buildEmployeeLeaveBalance(
                $employee,
                $filters['leaveYearOffset'],
                $filters['leaveTypeId'],
            )
            : null;

        return Inertia::render('LeaveBalances/Index', [
            'employee' => $report['employee'] ?? null,
            'leaveYears' => $report['leaveYears'] ?? [],
            'selectedLeaveYear' => $report['selectedLeaveYear'] ?? null,
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'employees' => $this->employeeOptions($filters['departmentId'], $employee),
            'filterLeaveTypes' => LeaveType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'department_id' => $filters['departmentId'],
                'employee_id' => $employee?->id,
                'leave_type_id' => $filters['leaveTypeId'],
                'leave_year_offset' => $report['selectedLeaveYear']['offset'] ?? $filters['leaveYearOffset'],
            ],
        ]);
    }

    public function export(Request $request, LeaveBalanceExportService $exportService): StreamedResponse
    {
        $filters = $this->resolveFilters($request);

        abort_unless($filters['employeeId'], 422, 'Select an employee before exporting.');

        return $exportService->download(
            $filters['leaveYearOffset'],
            $filters['departmentId'],
            $filters['employeeId'],
            $filters['leaveTypeId'],
        );
    }

    public function exportAll(Request $request, LeaveBalanceExportService $exportService): StreamedResponse
    {
        $departmentId = $request->filled('department_id') ? $request->integer('department_id') : null;

        return $exportService->downloadAllEmployeesUsed($departmentId);
    }

    /**
     * @return array{
     *     departmentId: int|null,
     *     employeeId: int|null,
     *     leaveTypeId: int|null,
     *     leaveYearOffset: int
     * }
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'departmentId' => $request->filled('department_id') ? $request->integer('department_id') : null,
            'employeeId' => $request->filled('employee_id') ? $request->integer('employee_id') : null,
            'leaveTypeId' => $request->filled('leave_type_id') ? $request->integer('leave_type_id') : null,
            'leaveYearOffset' => max(0, $request->integer('leave_year_offset', 0)),
        ];
    }

    private function resolveEmployee(?int $departmentId, ?int $employeeId): ?Employee
    {
        $employeeQuery = Employee::query()
            ->where('is_active', true)
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name');

        $employee = $employeeId
            ? $employeeQuery->clone()->whereKey($employeeId)->first()
            : $employeeQuery->clone()->first();

        if (! $employee && $employeeId) {
            $employee = Employee::query()
                ->where('is_active', true)
                ->whereKey($employeeId)
                ->first();
        }

        return $employee;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function employeeOptions(?int $departmentId, ?Employee $employee): array
    {
        return Employee::query()
            ->where('is_active', true)
            ->when($departmentId, function ($query) use ($departmentId, $employee) {
                $query->where(function ($inner) use ($departmentId, $employee) {
                    $inner->where('department_id', $departmentId);

                    if ($employee) {
                        $inner->orWhere('id', $employee->id);
                    }
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'staff_id'])
            ->map(fn (Employee $item) => [
                'id' => $item->id,
                'label' => "{$item->name} ({$item->staff_id})",
            ])
            ->values()
            ->all();
    }
}
