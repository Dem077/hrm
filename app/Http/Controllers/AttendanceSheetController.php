<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Services\Attendance\AttendanceSheetService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSheetController extends Controller
{
    public function index(Request $request, AttendanceSheetService $sheetService): Response
    {
        $timezone = config('app.timezone', 'UTC');
        $today = now($timezone);

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString(), $timezone)->startOfDay()
            : $today->copy();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString(), $timezone)->startOfDay()
            : $from->copy();

        $departmentId = $request->filled('department_id')
            ? $request->integer('department_id')
            : null;

        $employeeId = $request->filled('employee_id')
            ? $request->integer('employee_id')
            : null;

        $includeAbsent = $request->boolean('include_absent');

        $maxDays = $includeAbsent
            ? AttendanceSheetService::MAX_DAYS_WITH_ABSENTS
            : AttendanceSheetService::MAX_DAYS_WITHOUT_ABSENTS;

        if ($from->diffInDays($to) > $maxDays) {
            $to = $from->copy()->addDays($maxDays);
        }

        $result = $sheetService->build($from, $to, $departmentId, $employeeId, $includeAbsent);

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

        return Inertia::render('AttendanceSheets/Index', [
            'rows' => $paginated,
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'employees' => Employee::query()
                ->where('is_active', true)
                ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
                ->orderBy('name')
                ->get(['id', 'name', 'staff_id'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'label' => "{$employee->name} ({$employee->staff_id})",
                ]),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'department_id' => $departmentId,
                'employee_id' => $employeeId,
                'include_absent' => $includeAbsent,
            ],
            'limits' => [
                'max_days' => $maxDays,
                'max_days_with_absents' => AttendanceSheetService::MAX_DAYS_WITH_ABSENTS,
                'max_days_without_absents' => AttendanceSheetService::MAX_DAYS_WITHOUT_ABSENTS,
            ],
        ]);
    }
}
