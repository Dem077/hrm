<?php

namespace App\Http\Controllers;

use App\Http\Requests\RemoveManualAttendancePunchRequest;
use App\Http\Requests\StoreManualAttendancePunchRequest;
use App\Models\Employee;
use App\Services\Attendance\AttendanceSheetService;
use App\Services\Attendance\ManualAttendancePunchService;
use App\Services\Attendance\PayrollPeriodService;
use App\Support\StructureNodeOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSheetController extends Controller
{
    public function index(Request $request, AttendanceSheetService $sheetService, PayrollPeriodService $payrollPeriodService): Response
    {
        $timezone = config('app.timezone', 'UTC');
        $today = now($timezone)->startOfDay();
        $payrollPeriod = $payrollPeriodService->presentation();

        $canViewAll = $request->user()?->can('attendance-sheet.view-all') ?? false;

        $departmentId = $canViewAll && $request->filled('department_id')
            ? $request->integer('department_id')
            : null;

        $employeeId = $canViewAll && $request->filled('employee_id')
            ? $request->integer('employee_id')
            : null;

        if (! $canViewAll) {
            $ownEmployeeId = $request->user()?->employee?->id;
            $employeeId = $ownEmployeeId ?? 0;
            $departmentId = null;
        }

        $isAllEmployees = $canViewAll && $employeeId === null;

        if ($isAllEmployees) {
            $from = $request->filled('from')
                ? Carbon::parse($request->string('from')->toString(), $timezone)->startOfDay()
                : $today->copy();
            $to = $from->copy();
            $payrollPeriodKey = 'day';
        } else {
            $payrollPeriodKey = (string) $request->input('payroll_period', '0');

            if ($payrollPeriodKey === 'custom' && ($request->filled('from') || $request->filled('to'))) {
                $from = $request->filled('from')
                    ? Carbon::parse($request->string('from')->toString(), $timezone)->startOfDay()
                    : Carbon::parse($payrollPeriod['current']['from'], $timezone)->startOfDay();

                $to = $request->filled('to')
                    ? Carbon::parse($request->string('to')->toString(), $timezone)->startOfDay()
                    : $from->copy();
            } elseif ($payrollPeriodKey === 'current') {
                $payrollPeriodKey = '0';
                $from = Carbon::parse($payrollPeriod['current']['from'], $timezone)->startOfDay();
                $to = Carbon::parse($payrollPeriod['current']['to'], $timezone)->startOfDay();
            } elseif ($payrollPeriodKey === 'previous') {
                $payrollPeriodKey = '1';
                $from = Carbon::parse($payrollPeriod['previous']['from'], $timezone)->startOfDay();
                $to = Carbon::parse($payrollPeriod['previous']['to'], $timezone)->startOfDay();
            } elseif (ctype_digit($payrollPeriodKey)) {
                $period = $payrollPeriodService->recentPeriodByOffset((int) $payrollPeriodKey)
                    ?? $payrollPeriodService->recentPeriodByOffset(0);

                $from = $period['from'];
                $to = $period['to'];
            } else {
                $payrollPeriodKey = '0';
                $from = Carbon::parse($payrollPeriod['current']['from'], $timezone)->startOfDay();
                $to = Carbon::parse($payrollPeriod['current']['to'], $timezone)->startOfDay();
            }
        }

        $maxDays = AttendanceSheetService::MAX_DAYS;

        if ($from->diffInDays($to) > $maxDays) {
            $to = $from->copy()->addDays($maxDays);
        }

        $result = $sheetService->build($from, $to, $departmentId, $employeeId);

        $page = max(1, $request->integer('page', 1));
        $perPage = 50;
        $rows = collect($result['rows']);
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values()->all(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $canAddPunch = $request->user()?->can('attendance-sheet.add-punch') ?? false;
        $canRemovePunch = $request->user()?->can('attendance-sheet.remove-punch') ?? false;
        $ownEmployeeId = $request->user()?->employee?->id;

        return Inertia::render('AttendanceSheets/Index', [
            'rows' => $paginated,
            'canViewAll' => $canViewAll,
            'canAddPunch' => $canAddPunch,
            'canRemovePunch' => $canRemovePunch,
            'singleDayOnly' => $isAllEmployees,
            'hasEmployeeProfile' => $ownEmployeeId !== null,
            'payrollPeriod' => $payrollPeriod,
            'punchEmployees' => $canAddPunch
                ? Employee::query()
                    ->where('is_active', true)
                    ->when(! $canViewAll, fn ($query) => $query->where('id', $ownEmployeeId))
                    ->orderBy('name')
                    ->get(['id', 'name', 'staff_id'])
                    ->map(fn (Employee $employee) => [
                        'id' => $employee->id,
                        'label' => "{$employee->name} ({$employee->staff_id})",
                    ])
                : [],
            'punchDefaults' => [
                'employee_id' => $canViewAll ? $employeeId : $ownEmployeeId,
                'duty_date' => $from->toDateString(),
            ],
            'departments' => $canViewAll
                ? StructureNodeOptions::active()
                : [],
            'employees' => $canViewAll
                ? StructureNodeOptions::constrainEmployeesByNode(
                    Employee::query()->where('is_active', true),
                    $departmentId,
                )
                    ->when($employeeId && $departmentId, function ($query) use ($employeeId) {
                        $query->orWhere('id', $employeeId);
                    })
                    ->orderBy('name')
                    ->get(['id', 'name', 'staff_id'])
                    ->map(fn (Employee $employee) => [
                        'id' => $employee->id,
                        'label' => "{$employee->name} ({$employee->staff_id})",
                    ])
                : [],
            'filters' => [
                'payroll_period' => $payrollPeriodKey,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'department_id' => $departmentId,
                'employee_id' => $employeeId,
            ],
            'limits' => [
                'max_days' => $maxDays,
            ],
        ]);
    }

    public function storeManualPunch(
        StoreManualAttendancePunchRequest $request,
        ManualAttendancePunchService $punchService,
    ): RedirectResponse {
        $timezone = config('app.timezone', 'UTC');
        $employee = $request->employee();

        abort_unless($employee->is_active, 422, 'Punches cannot be added for inactive employees.');

        $punchService->create(
            $employee,
            $request->punchedAt($timezone),
            $request->integer('punch_state'),
            $request->string('reason')->toString(),
            $request->user(),
        );

        return back()->with('success', 'Manual punch added successfully.');
    }

    public function destroyManualPunches(
        RemoveManualAttendancePunchRequest $request,
        ManualAttendancePunchService $punchService,
    ): RedirectResponse {
        $canViewAll = $request->user()?->can('attendance-sheet.view-all') ?? false;

        $logs = $punchService->resolveRemovableLogs(
            $request->input('punch_log_ids', []),
            $request->user(),
            $canViewAll,
        );

        $punchService->removeMany(
            $logs,
            $request->string('reason')->toString(),
            $request->user(),
        );

        return back()->with('success', 'Punch record(s) removed successfully.');
    }
}
