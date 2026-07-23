<?php

namespace App\Services\Overtime;

use App\Enums\LeaveApprovalStepStatus;
use App\Enums\OvertimeRequestStatus;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Attendance\AttendanceSheetService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OvertimeRequestService
{
    public function __construct(
        private readonly OvertimeApprovalWorkflowService $overtimeApprovalWorkflowService,
        private readonly AttendanceSheetService $attendanceSheetService,
    ) {}

    /**
     * Days where the employee stayed past duty end and still has claimable OT hours.
     *
     * @return list<array{
     *     overtime_date: string,
     *     label: string,
     *     duty_end_time: string,
     *     check_out_time: string,
     *     start_time: string,
     *     end_time: string,
     *     worked_hours: float,
     *     claimed_hours: float,
     *     available_hours: float
     * }>
     */
    public function eligibleOvertimeDays(Employee $employee, ?CarbonInterface $referenceDate = null): array
    {
        $timezone = config('app.timezone', 'UTC');
        $to = ($referenceDate ?? now($timezone))->copy()->timezone($timezone)->startOfDay();
        $from = $to->copy()->subDays(AttendanceSheetService::MAX_DAYS);

        $attendance = $this->attendanceSheetService->build($from, $to, null, $employee->id);
        $claimedByDate = $this->claimedHoursByDate($employee->id, $from, $to);

        $eligible = [];

        foreach ($attendance['rows'] as $row) {
            $window = $this->afterDutyOvertimeWindow($row);

            if ($window === null) {
                continue;
            }

            $date = $window['overtime_date'];
            $claimed = (float) ($claimedByDate[$date] ?? 0);
            $available = round(max(0, $window['worked_hours'] - $claimed), 2);

            if ($available < 0.25) {
                continue;
            }

            $eligible[] = [
                'overtime_date' => $date,
                'label' => sprintf(
                    '%s · %s–%s · %s h available',
                    $date,
                    $window['start_time'],
                    $window['end_time'],
                    number_format($available, 2, '.', ''),
                ),
                'duty_end_time' => $window['start_time'],
                'check_out_time' => $window['end_time'],
                'start_time' => $window['start_time'],
                'end_time' => $window['end_time'],
                'worked_hours' => $window['worked_hours'],
                'claimed_hours' => $claimed,
                'available_hours' => $available,
            ];
        }

        return array_values(array_reverse($eligible));
    }

    /**
     * @return array{
     *     overtime_date: string,
     *     start_time: string,
     *     end_time: string,
     *     worked_hours: float,
     *     claimed_hours: float,
     *     available_hours: float
     * }|null
     */
    public function eligibilityForDate(Employee $employee, CarbonInterface $overtimeDate): ?array
    {
        $timezone = config('app.timezone', 'UTC');
        $date = $overtimeDate->copy()->timezone($timezone)->startOfDay();

        $attendance = $this->attendanceSheetService->build($date, $date, null, $employee->id);
        $row = collect($attendance['rows'])->firstWhere('date', $date->toDateString());

        if (! is_array($row)) {
            return null;
        }

        $window = $this->afterDutyOvertimeWindow($row);

        if ($window === null) {
            return null;
        }

        $claimed = $this->claimedHoursForDate($employee->id, $date);
        $available = round(max(0, $window['worked_hours'] - $claimed), 2);

        if ($available < 0.25) {
            return null;
        }

        return [
            'overtime_date' => $window['overtime_date'],
            'start_time' => $window['start_time'],
            'end_time' => $window['end_time'],
            'worked_hours' => $window['worked_hours'],
            'claimed_hours' => $claimed,
            'available_hours' => $available,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{overtime_date: string, start_time: string, end_time: string, worked_hours: float}|null
     */
    public function afterDutyOvertimeWindow(array $row): ?array
    {
        $date = (string) ($row['date'] ?? '');
        $dutyStart = trim((string) ($row['duty_start_time'] ?? ''));
        $dutyEnd = trim((string) ($row['duty_end_time'] ?? ''));
        $checkOutRaw = $row['check_out'] ?? null;

        if ($date === '' || $dutyEnd === '' || $dutyEnd === '—' || ! $checkOutRaw) {
            return null;
        }

        $timezone = config('app.timezone', 'UTC');

        try {
            $dutyEndAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.substr($dutyEnd, 0, 5), $timezone);
            $checkOutAt = Carbon::parse($checkOutRaw)->timezone($timezone);
        } catch (\Throwable) {
            return null;
        }

        if (! $dutyEndAt || ! $checkOutAt) {
            return null;
        }

        if ($dutyStart !== '' && $dutyStart !== '—' && substr($dutyStart, 0, 5) > substr($dutyEnd, 0, 5)) {
            $dutyEndAt->addDay();
        }

        if ($checkOutAt->lte($dutyEndAt)) {
            return null;
        }

        $minutes = $dutyEndAt->diffInMinutes($checkOutAt);

        if ($minutes < 15) {
            return null;
        }

        return [
            'overtime_date' => $date,
            'start_time' => $dutyEndAt->format('H:i'),
            'end_time' => $checkOutAt->format('H:i'),
            'worked_hours' => round($minutes / 60, 2),
        ];
    }

    /**
     * @return array<string, float>
     */
    protected function claimedHoursByDate(int $employeeId, CarbonInterface $from, CarbonInterface $to): array
    {
        return OvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [
                OvertimeRequestStatus::Pending,
                OvertimeRequestStatus::PendingHr,
                OvertimeRequestStatus::Approved,
            ])
            ->whereBetween('overtime_date', [$from->toDateString(), $to->toDateString()])
            ->get(['overtime_date', 'hours'])
            ->groupBy(fn (OvertimeRequest $request) => $request->overtime_date->toDateString())
            ->map(fn (Collection $group) => round((float) $group->sum('hours'), 2))
            ->all();
    }

    protected function claimedHoursForDate(int $employeeId, CarbonInterface $date): float
    {
        $hours = OvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [
                OvertimeRequestStatus::Pending,
                OvertimeRequestStatus::PendingHr,
                OvertimeRequestStatus::Approved,
            ])
            ->whereDate('overtime_date', $date->toDateString())
            ->sum('hours');

        return round((float) $hours, 2);
    }

    public function generateRecordNumber(?Carbon $issuedAt = null): string
    {
        $issuedAt ??= now(config('app.timezone', 'UTC'));
        $year = $issuedAt->year;
        $prefix = "HR/{$year}/OT/";

        return DB::transaction(function () use ($prefix): string {
            $latestSequence = OvertimeRequest::query()
                ->where('record_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->pluck('record_number')
                ->map(fn (string $recordNumber) => (int) Str::afterLast($recordNumber, '/'))
                ->max() ?? 0;

            return $prefix.str_pad((string) ($latestSequence + 1), 2, '0', STR_PAD_LEFT);
        });
    }

    public function resolveApprover(Employee $employee): ?Employee
    {
        return $this->overtimeApprovalWorkflowService->resolveFirstApprover($employee)
            ?? $this->overtimeApprovalWorkflowService->resolveLegacyApprover($employee);
    }

    public function syncApprover(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        if (! $overtimeRequest->isPendingManagerApproval()) {
            return $overtimeRequest;
        }

        if ($overtimeRequest->approvalSteps()->exists()) {
            return $this->overtimeApprovalWorkflowService->syncCurrentApprover($overtimeRequest);
        }

        $overtimeRequest->loadMissing([
            'employee.manager',
            'employee.grade.level.node.headGrades',
            'employee.grade.level.node.parent.headGrades',
        ]);

        if (! $overtimeRequest->employee) {
            return $overtimeRequest;
        }

        $approver = $this->resolveApprover($overtimeRequest->employee);

        if ($overtimeRequest->approver_employee_id !== $approver?->id) {
            $overtimeRequest->update(['approver_employee_id' => $approver?->id]);
            $overtimeRequest->setRelation('approver', $approver);
        }

        return $overtimeRequest;
    }

    public function syncAllPendingApprovers(): void
    {
        OvertimeRequest::query()
            ->where('status', OvertimeRequestStatus::Pending)
            ->with([
                'employee.manager',
                'employee.grade.level.node.headGrades',
                'employee.grade.level.node.parent.headGrades',
            ])
            ->chunkById(100, function ($requests): void {
                foreach ($requests as $request) {
                    $this->syncApprover($request);
                }
            });
    }

    /**
     * Sum of approved overtime hours for an employee in a date range (inclusive).
     */
    public function approvedHoursInPeriod(int $employeeId, CarbonInterface $from, CarbonInterface $to): float
    {
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $hours = OvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', OvertimeRequestStatus::Approved)
            ->whereBetween('overtime_date', [$from->toDateString(), $to->toDateString()])
            ->sum('hours');

        return round((float) $hours, 2);
    }

    /**
     * @return Builder<OvertimeRequest>
     */
    public function accessibleRequestsQuery(User $user): Builder
    {
        $employee = $user->employee;
        $canViewAll = $user->can('overtime-requests.view-all');
        $canApproveHr = $user->can('overtime-requests.approve-hr');

        return OvertimeRequest::query()
            ->with([
                'employee:id,name,staff_id,grade_id',
                'employee.grade.level.group',
                'employee.grade.level.node.group',
                'approver:id,name,staff_id',
                'managerReviewedBy:id,name,staff_id',
                'reviewedBy:id,name,staff_id',
            ])
            ->when(! $canViewAll && $employee, function (Builder $query) use ($employee, $canApproveHr): void {
                $query->where(function (Builder $inner) use ($employee, $canApproveHr): void {
                    $inner->where('employee_id', $employee->id)
                        ->orWhere(fn (Builder $approverQuery) => $this->applyPendingManagerApproverScope($approverQuery, $employee));

                    if ($canApproveHr) {
                        $inner->orWhere('status', OvertimeRequestStatus::PendingHr);
                    }

                    $inner->orWhere('manager_reviewed_by_employee_id', $employee->id);
                });
            })
            ->when(! $canViewAll && ! $employee && $canApproveHr, fn (Builder $query) => $query->where('status', OvertimeRequestStatus::PendingHr))
            ->when(! $canViewAll && ! $employee && ! $canApproveHr, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->latest('overtime_date')
            ->latest('id');
    }

    public function applyPendingManagerApproverScope(Builder $query, Employee $approver): Builder
    {
        return $query
            ->where('status', OvertimeRequestStatus::Pending)
            ->where('employee_id', '!=', $approver->id)
            ->where('approver_employee_id', $approver->id);
    }

    public function pendingManagerApprovalCount(Employee $approver): int
    {
        return $this->applyPendingManagerApproverScope(OvertimeRequest::query(), $approver)->count();
    }

    public function isManagerApprover(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $overtimeRequest->isPendingManagerApproval()) {
            return false;
        }

        $employee = $user->employee;

        if (! $employee || $overtimeRequest->employee_id === $employee->id) {
            return false;
        }

        $this->syncApprover($overtimeRequest);

        return (int) $overtimeRequest->approver_employee_id === (int) $employee->id;
    }

    public function canView(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if ($user->can('overtime-requests.view-all')) {
            return true;
        }

        if ($user->can('overtime-requests.approve-hr') && $overtimeRequest->isPendingHrApproval()) {
            return true;
        }

        $employee = $user->employee;

        if (! $employee) {
            return false;
        }

        return $overtimeRequest->employee_id === $employee->id
            || $this->isManagerApprover($user, $overtimeRequest)
            || $overtimeRequest->manager_reviewed_by_employee_id === $employee->id
            || $overtimeRequest->approvalSteps()
                ->where(function ($query) use ($employee): void {
                    $query->where('approver_employee_id', $employee->id)
                        ->orWhere('acted_by_employee_id', $employee->id);
                })
                ->exists();
    }

    public function canApprove(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $user->can('overtime-requests.approve')) {
            return false;
        }

        return $this->isManagerApprover($user, $overtimeRequest);
    }

    public function canApproveHr(User $user, OvertimeRequest $overtimeRequest): bool
    {
        return $user->can('overtime-requests.approve-hr') && $overtimeRequest->isPendingHrApproval();
    }

    public function canCancel(User $user, OvertimeRequest $overtimeRequest): bool
    {
        if (! $user->can('overtime-requests.cancel')) {
            return false;
        }

        if (! $overtimeRequest->isPending()) {
            return false;
        }

        $employee = $user->employee;

        return $employee && $overtimeRequest->employee_id === $employee->id;
    }

    public function approveByManager(User $user, OvertimeRequest $overtimeRequest, ?string $reviewNotes = null): void
    {
        $actor = $user->employee;

        if (! $actor) {
            throw ValidationException::withMessages([
                'approver' => 'Your login account is not linked to an employee record.',
            ]);
        }

        if ($overtimeRequest->approvalSteps()->exists()) {
            $this->overtimeApprovalWorkflowService->approveCurrentStructureStep($overtimeRequest, $actor, $reviewNotes);

            return;
        }

        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::PendingHr,
            'manager_reviewed_by_employee_id' => $actor->id,
            'manager_reviewed_at' => now(),
            'manager_review_notes' => $reviewNotes,
        ]);
    }

    public function approveByHr(User $user, OvertimeRequest $overtimeRequest, ?string $reviewNotes = null): void
    {
        $actor = $user->employee;

        if ($overtimeRequest->approvalSteps()->exists()) {
            $this->overtimeApprovalWorkflowService->markHrStep(
                $overtimeRequest,
                LeaveApprovalStepStatus::Approved,
                $actor,
                $reviewNotes,
            );
        }

        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::Approved,
            'reviewed_by_employee_id' => $actor?->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);
    }

    public function rejectByManager(User $user, OvertimeRequest $overtimeRequest, ?string $reviewNotes = null): void
    {
        $actor = $user->employee;

        if (! $actor) {
            throw ValidationException::withMessages([
                'approver' => 'Your login account is not linked to an employee record.',
            ]);
        }

        if ($overtimeRequest->approvalSteps()->exists()) {
            $this->overtimeApprovalWorkflowService->rejectCurrentStructureStep($overtimeRequest, $actor, $reviewNotes);

            return;
        }

        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::Rejected,
            'manager_reviewed_by_employee_id' => $actor->id,
            'manager_reviewed_at' => now(),
            'manager_review_notes' => $reviewNotes,
        ]);
    }

    public function rejectByHr(User $user, OvertimeRequest $overtimeRequest, ?string $reviewNotes = null): void
    {
        $actor = $user->employee;

        if ($overtimeRequest->approvalSteps()->exists()) {
            $this->overtimeApprovalWorkflowService->markHrStep(
                $overtimeRequest,
                LeaveApprovalStepStatus::Rejected,
                $actor,
                $reviewNotes,
            );
        }

        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::Rejected,
            'reviewed_by_employee_id' => $actor?->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatOvertimeRequest(OvertimeRequest $overtimeRequest): array
    {
        $this->syncApprover($overtimeRequest);

        $overtimeRequest->loadMissing([
            'employee.grade.level.group',
            'employee.grade.level.node.group',
            'approver:id,name,staff_id',
            'managerReviewedBy:id,name,staff_id',
            'reviewedBy:id,name,staff_id',
            'approvalSteps.approver:id,name,staff_id',
            'approvalSteps.actedBy:id,name,staff_id',
        ]);

        $approver = $overtimeRequest->approver;

        if ($overtimeRequest->isPendingManagerApproval() && $overtimeRequest->employee && ! $approver) {
            $approver = $this->resolveApprover($overtimeRequest->employee);
        }

        $approverLabel = $overtimeRequest->isPendingHrApproval()
            ? 'Human Resources'
            : ($approver?->name ?? null);

        $employeePath = $overtimeRequest->employee?->grade?->resolvePath();

        return [
            'id' => $overtimeRequest->id,
            'record_number' => $overtimeRequest->record_number,
            'employee_id' => $overtimeRequest->employee_id,
            'employee' => $overtimeRequest->employee ? [
                'id' => $overtimeRequest->employee->id,
                'name' => $overtimeRequest->employee->name,
                'staff_id' => $overtimeRequest->employee->staff_id,
                'department' => ($employeePath['node'] ?? null) ?: ($employeePath['group'] ?? null),
            ] : null,
            'overtime_date' => $overtimeRequest->overtime_date?->toDateString(),
            'start_time' => $overtimeRequest->start_time
                ? Carbon::parse($overtimeRequest->start_time)->format('H:i')
                : null,
            'end_time' => $overtimeRequest->end_time
                ? Carbon::parse($overtimeRequest->end_time)->format('H:i')
                : null,
            'hours' => (float) $overtimeRequest->hours,
            'reason' => $overtimeRequest->reason,
            'status' => $overtimeRequest->status->value,
            'status_label' => $overtimeRequest->status->label(),
            'status_color' => $overtimeRequest->status->color(),
            'approver_employee_id' => $approver?->id,
            'approver' => $approver ? [
                'id' => $approver->id,
                'name' => $approver->name,
                'staff_id' => $approver->staff_id,
            ] : null,
            'approver_label' => $approverLabel,
            'approval_steps' => $overtimeRequest->approvalSteps->map(fn ($step) => [
                'id' => $step->id,
                'step_order' => $step->step_order,
                'step_key' => $step->step_key->value,
                'label' => $step->label,
                'status' => $step->status->value,
                'status_label' => $step->status->label(),
                'status_color' => $step->status->color(),
                'approver' => $step->approver ? [
                    'id' => $step->approver->id,
                    'name' => $step->approver->name,
                    'staff_id' => $step->approver->staff_id,
                ] : null,
                'acted_by' => $step->actedBy ? [
                    'id' => $step->actedBy->id,
                    'name' => $step->actedBy->name,
                    'staff_id' => $step->actedBy->staff_id,
                ] : null,
                'acted_at' => $step->acted_at?->toIso8601String(),
                'notes' => $step->notes,
            ])->values()->all(),
            'manager_reviewed_by_employee_id' => $overtimeRequest->manager_reviewed_by_employee_id,
            'manager_reviewed_by' => $overtimeRequest->managerReviewedBy ? [
                'id' => $overtimeRequest->managerReviewedBy->id,
                'name' => $overtimeRequest->managerReviewedBy->name,
                'staff_id' => $overtimeRequest->managerReviewedBy->staff_id,
            ] : null,
            'manager_reviewed_at' => $overtimeRequest->manager_reviewed_at?->toIso8601String(),
            'manager_review_notes' => $overtimeRequest->manager_review_notes,
            'reviewed_by_employee_id' => $overtimeRequest->reviewed_by_employee_id,
            'reviewed_by' => $overtimeRequest->reviewedBy ? [
                'id' => $overtimeRequest->reviewedBy->id,
                'name' => $overtimeRequest->reviewedBy->name,
                'staff_id' => $overtimeRequest->reviewedBy->staff_id,
            ] : null,
            'reviewed_at' => $overtimeRequest->reviewed_at?->toIso8601String(),
            'review_notes' => $overtimeRequest->review_notes,
            'created_at' => $overtimeRequest->created_at?->toIso8601String(),
        ];
    }
}
