<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAssignDutyRosterRequest;
use App\Http\Requests\StoreDutyRosterRequest;
use App\Http\Requests\UpdateDutyRosterRequest;
use App\Models\Department;
use App\Models\DutyRoster;
use App\Models\DutyShiftTemplate;
use App\Models\Employee;
use App\Services\Attendance\DutyRosterAssignmentService;
use App\Services\Attendance\DutyRosterScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DutyRosterController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = $this->scope($request);
        $canViewAll = $scope->canViewAll();

        $from = $request->string('from')->toString() ?: now()->startOfWeek()->toDateString();
        $to = $request->string('to')->toString() ?: now()->endOfWeek()->toDateString();

        $departmentId = $canViewAll && $request->filled('department_id')
            ? $request->integer('department_id')
            : null;

        $employeeId = $canViewAll && $request->filled('employee_id')
            ? $request->integer('employee_id')
            : null;

        $departmentId = $scope->resolveFilterDepartment($departmentId);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $entries = DutyRoster::query()
            ->with(['employee.department:id,name'])
            ->whereHas('employee', function ($query) use ($departmentId, $employeeId, $scope) {
                $scope->applyShiftEmployeeScope($query);

                if ($departmentId) {
                    $query->where('department_id', $departmentId);
                }

                if ($employeeId) {
                    $query->where('id', $employeeId);
                }
            })
            ->whereDate('duty_date', '>=', $from)
            ->whereDate('duty_date', '<=', $to)
            ->orderBy('duty_date')
            ->orderBy('employee_id')
            ->get()
            ->sortBy(fn (DutyRoster $entry) => [$entry->duty_date->toDateString(), $entry->employee?->name ?? ''])
            ->values()
            ->map(fn (DutyRoster $entry) => $entry->toPresentationArray());

        $scopedDepartmentId = $scope->scopedDepartmentId();

        return Inertia::render('DutyRosters/Index', [
            'entries' => $entries,
            'canViewAll' => $canViewAll,
            'scopedDepartmentId' => $scopedDepartmentId,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'department_id' => $departmentId,
                'employee_id' => $employeeId,
            ],
            'departments' => $canViewAll
                ? Department::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : ($scopedDepartmentId
                    ? Department::query()->whereKey($scopedDepartmentId)->get(['id', 'name'])
                    : []),
            'shiftEmployees' => $this->shiftEmployees($scope, $departmentId),
            'allShiftEmployees' => $this->shiftEmployees($scope),
            'dutyShiftTemplates' => DutyShiftTemplate::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (DutyShiftTemplate $template) => $template->toPresentationArray()),
            'emptyEntry' => $this->emptyEntry(),
            'emptyTemplate' => $this->emptyTemplate(),
            'emptyBulkAssign' => $this->emptyBulkAssign(),
        ]);
    }

    public function bulkAssign(BulkAssignDutyRosterRequest $request, DutyRosterAssignmentService $assignmentService): RedirectResponse
    {
        $assigned = $assignmentService->assignToDates(
            $request->input('employee_ids'),
            $request->resolvedDates(),
            $request->dutyTimes(),
            $request->input('notes'),
        );

        return back()->with('success', "Assigned duty to {$assigned} employee day(s) successfully.");
    }

    public function store(StoreDutyRosterRequest $request): RedirectResponse
    {
        DutyRoster::query()->create($request->validated());

        return back()->with('success', 'Duty roster entry created successfully.');
    }

    public function update(UpdateDutyRosterRequest $request, DutyRoster $dutyRoster): RedirectResponse
    {
        $this->authorizeRosterEntry($request, $dutyRoster);

        $dutyRoster->update($request->validated());

        return back()->with('success', 'Duty roster entry updated successfully.');
    }

    public function destroy(Request $request, DutyRoster $dutyRoster): RedirectResponse
    {
        $this->authorizeRosterEntry($request, $dutyRoster);

        $dutyRoster->delete();

        return back()->with('success', 'Duty roster entry deleted successfully.');
    }

    protected function scope(Request $request): DutyRosterScopeService
    {
        return new DutyRosterScopeService($request->user());
    }

    protected function authorizeRosterEntry(Request $request, DutyRoster $dutyRoster): void
    {
        abort_unless(
            $this->scope($request)->employeeIsAccessible($dutyRoster->employee_id),
            403,
            'You can only manage duty roster entries for staff in your department.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyEntry(): array
    {
        return [
            'id' => null,
            'employee_id' => null,
            'duty_date' => now()->toDateString(),
            'duty_start_time' => '09:00',
            'duty_end_time' => '18:00',
            'grace_minutes' => 15,
            'notes' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyTemplate(): array
    {
        return [
            'id' => null,
            'name' => '',
            'duty_start_time' => '09:00',
            'duty_end_time' => '18:00',
            'grace_minutes' => 15,
            'notes' => '',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyBulkAssign(): array
    {
        return [
            'employee_ids' => [],
            'date_mode' => 'range',
            'from_date' => now()->toDateString(),
            'to_date' => now()->toDateString(),
            'duty_dates' => [],
            'duty_shift_template_id' => null,
            'duty_start_time' => '09:00',
            'duty_end_time' => '18:00',
            'grace_minutes' => 15,
            'notes' => '',
            'skip_weekends' => false,
        ];
    }

    /**
     * @return list<array{id: int, label: string, department_id: int|null}>
     */
    protected function shiftEmployees(DutyRosterScopeService $scope, ?int $departmentId = null): array
    {
        return $scope->shiftEmployeeQuery()
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->get(['id', 'staff_id', 'name', 'department_id'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'label' => "{$employee->name} ({$employee->staff_id})",
                'department_id' => $employee->department_id,
            ])
            ->all();
    }
}
