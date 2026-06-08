<?php

namespace App\Services\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\ZktAttendanceLog;
use App\Support\DateFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaveRequestService
{
    public function generateRecordNumber(?Carbon $issuedAt = null): string
    {
        $issuedAt ??= now(config('app.timezone', 'UTC'));
        $year = $issuedAt->year;
        $prefix = "HR/{$year}/FORM/";

        return DB::transaction(function () use ($prefix): string {
            $latestSequence = LeaveRequest::query()
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
        $employee->loadMissing(['manager', 'department.headEmployee']);

        if ($employee->manager_id) {
            $manager = $employee->manager ?? Employee::query()->find($employee->manager_id);

            if ($manager && $manager->id !== $employee->id) {
                return $manager;
            }
        }

        $departmentHead = $employee->department?->headEmployee;

        if ($departmentHead && $departmentHead->id !== $employee->id) {
            return $departmentHead;
        }

        return null;
    }

    public function syncApprover(LeaveRequest $leaveRequest): LeaveRequest
    {
        if (! $leaveRequest->isPendingManagerApproval()) {
            return $leaveRequest;
        }

        $leaveRequest->loadMissing(['employee.manager', 'employee.department.headEmployee']);

        if (! $leaveRequest->employee) {
            return $leaveRequest;
        }

        $approver = $this->resolveApprover($leaveRequest->employee);

        if ($leaveRequest->approver_employee_id !== $approver?->id) {
            $leaveRequest->update(['approver_employee_id' => $approver?->id]);
            $leaveRequest->setRelation('approver', $approver);
        }

        return $leaveRequest;
    }

    public function syncAllPendingApprovers(): void
    {
        LeaveRequest::query()
            ->where('status', LeaveRequestStatus::Pending)
            ->with(['employee.manager', 'employee.department.headEmployee'])
            ->chunkById(100, function ($leaveRequests): void {
                foreach ($leaveRequests as $leaveRequest) {
                    $this->syncApprover($leaveRequest);
                }
            });
    }

    public function calculateDaysCount(Carbon $startDate, Carbon $endDate): int
    {
        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return (int) $startDate->diffInDays($endDate) + 1;
    }

    /**
     * @return Builder<LeaveRequest>
     */
    public function overlappingLeaveQuery(
        int $employeeId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $ignoreLeaveRequestId = null,
    ): Builder {
        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->whereNotIn('status', [
                LeaveRequestStatus::Rejected,
                LeaveRequestStatus::Cancelled,
            ])
            ->when($ignoreLeaveRequestId, fn (Builder $query) => $query->whereKeyNot($ignoreLeaveRequestId))
            ->where('start_date', '<=', $endDate->toDateString())
            ->where('end_date', '>=', $startDate->toDateString());
    }

    public function findOverlappingLeave(
        int $employeeId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $ignoreLeaveRequestId = null,
    ): ?LeaveRequest {
        return $this->overlappingLeaveQuery($employeeId, $startDate, $endDate, $ignoreLeaveRequestId)
            ->with('leaveType:id,name')
            ->first();
    }

    public function overlappingLeaveMessage(LeaveRequest $leaveRequest): string
    {
        $leaveRequest->loadMissing('leaveType:id,name');

        $leaveType = $leaveRequest->leaveType?->name ?? 'Leave';
        $start = DateFormatter::formatDate($leaveRequest->start_date);
        $end = DateFormatter::formatDate($leaveRequest->end_date);

        return "These dates overlap with an existing {$leaveType} request ({$start} to {$end}).";
    }

    /**
     * @return Collection<int, non-empty-string>
     */
    public function checkInDatesDuringLeave(Employee $employee, Carbon $startDate, Carbon $endDate): Collection
    {
        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        if (! $employee->staff_id) {
            return collect();
        }

        $timezone = config('app.timezone', 'UTC');

        return ZktAttendanceLog::query()
            ->where('device_user_id', $employee->staff_id)
            ->whereBetween('punched_at', [
                $startDate->copy()->timezone($timezone)->startOfDay(),
                $endDate->copy()->timezone($timezone)->endOfDay(),
            ])
            ->get(['punched_at'])
            ->map(fn (ZktAttendanceLog $log) => $log->punched_at->timezone($timezone)->toDateString())
            ->unique()
            ->sort()
            ->values();
    }

    public function checkInDatesDuringLeaveForEmployee(
        int $employeeId,
        Carbon $startDate,
        Carbon $endDate,
    ): Collection {
        $employee = Employee::query()->find($employeeId);

        if (! $employee) {
            return collect();
        }

        return $this->checkInDatesDuringLeave($employee, $startDate, $endDate);
    }

    /**
     * @param  Collection<int, non-empty-string>  $dates
     */
    public function punchConflictMessage(Collection $dates): string
    {
        if ($dates->isEmpty()) {
            return '';
        }

        $formattedDates = $dates
            ->map(fn (string $date) => DateFormatter::formatDate($date))
            ->join(', ');

        return "Attendance punch records exist on {$formattedDates}. Continue with this leave anyway?";
    }

    /**
     * @return array{has_punches: bool, dates: list<string>, message: string|null}
     */
    public function punchConflictSummary(int $employeeId, Carbon $startDate, Carbon $endDate): array
    {
        $dates = $this->checkInDatesDuringLeaveForEmployee($employeeId, $startDate, $endDate);

        return [
            'has_punches' => $dates->isNotEmpty(),
            'dates' => $dates->all(),
            'message' => $dates->isNotEmpty() ? $this->punchConflictMessage($dates) : null,
        ];
    }

    public function recordLeaveForEmployee(
        Employee $employee,
        int $leaveTypeId,
        Carbon $startDate,
        Carbon $endDate,
        string $reason,
        ?string $documentPath = null,
        ?Employee $recordedBy = null,
        ?string $reviewNotes = null,
    ): LeaveRequest {
        return LeaveRequest::query()->create([
            'record_number' => $this->generateRecordNumber(),
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_count' => $this->calculateDaysCount($startDate, $endDate),
            'reason' => $reason,
            'document_path' => $documentPath,
            'status' => LeaveRequestStatus::Approved,
            'approver_employee_id' => null,
            'reviewed_by_employee_id' => $recordedBy?->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes ?: ($recordedBy ? "Recorded by {$recordedBy->name}" : 'Recorded by HR'),
        ]);
    }

    /**
     * @return Builder<LeaveType>
     */
    public function visibleLeaveTypesQuery(bool $forEmployees = true): Builder
    {
        return LeaveType::query()
            ->where('is_active', true)
            ->when($forEmployees, fn (Builder $query) => $query->where('is_visible_to_employees', true))
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @return Builder<LeaveRequest>
     */
    public function accessibleRequestsQuery(User $user): Builder
    {
        $employee = $user->employee;
        $canViewAll = $user->can('leave-requests.view-all');
        $canApproveHr = $user->can('leave-requests.approve-hr');

        return LeaveRequest::query()
            ->with([
                'employee:id,name,staff_id,department_id',
                'employee.department:id,name',
                'leaveType:id,name,requires_document',
                'approver:id,name,staff_id',
                'managerReviewedBy:id,name,staff_id',
                'reviewedBy:id,name,staff_id',
            ])
            ->when(! $canViewAll && $employee, function (Builder $query) use ($employee, $canApproveHr): void {
                $query->where(function (Builder $inner) use ($employee, $canApproveHr): void {
                    $inner->where('employee_id', $employee->id)
                        ->orWhere(fn (Builder $approverQuery) => $this->applyPendingManagerApproverScope($approverQuery, $employee));

                    if ($canApproveHr) {
                        $inner->orWhere('status', LeaveRequestStatus::PendingHr);
                    }

                    $inner->orWhere('manager_reviewed_by_employee_id', $employee->id);
                });
            })
            ->when(! $canViewAll && ! $employee && $canApproveHr, fn (Builder $query) => $query->where('status', LeaveRequestStatus::PendingHr))
            ->when(! $canViewAll && ! $employee && ! $canApproveHr, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->latest('start_date')
            ->latest('id');
    }

    public function applyPendingManagerApproverScope(Builder $query, Employee $approver): Builder
    {
        return $query
            ->where('status', LeaveRequestStatus::Pending)
            ->where('employee_id', '!=', $approver->id)
            ->where(function (Builder $inner) use ($approver): void {
                $inner->whereHas('employee', function (Builder $employeeQuery) use ($approver): void {
                    $employeeQuery
                        ->where('manager_id', $approver->id)
                        ->whereColumn('manager_id', '!=', 'employees.id');
                })->orWhereHas('employee', function (Builder $employeeQuery) use ($approver): void {
                    $employeeQuery
                        ->where(function (Builder $managerQuery): void {
                            $managerQuery->whereNull('manager_id')
                                ->orWhereColumn('manager_id', 'employees.id');
                        })
                        ->whereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('head_employee_id', $approver->id));
                });
            });
    }

    public function pendingManagerApprovalCount(Employee $approver): int
    {
        return $this->applyPendingManagerApproverScope(LeaveRequest::query(), $approver)->count();
    }

    public function isManagerApprover(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $leaveRequest->isPendingManagerApproval()) {
            return false;
        }

        $employee = $user->employee;

        if (! $employee || $leaveRequest->employee_id === $employee->id) {
            return false;
        }

        $leaveRequest->loadMissing(['employee.manager', 'employee.department.headEmployee']);

        if (! $leaveRequest->employee) {
            return false;
        }

        $expectedApprover = $this->resolveApprover($leaveRequest->employee);

        return $expectedApprover && $expectedApprover->id === $employee->id;
    }

    public function canView(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->can('leave-requests.view-all')) {
            return true;
        }

        if ($user->can('leave-requests.approve-hr') && $leaveRequest->isPendingHrApproval()) {
            return true;
        }

        $employee = $user->employee;

        if (! $employee) {
            return false;
        }

        return $leaveRequest->employee_id === $employee->id
            || $this->isManagerApprover($user, $leaveRequest)
            || $leaveRequest->manager_reviewed_by_employee_id === $employee->id;
    }

    public function canApprove(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->can('leave-requests.approve')) {
            return false;
        }

        return $this->isManagerApprover($user, $leaveRequest);
    }

    public function canApproveHr(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave-requests.approve-hr') && $leaveRequest->isPendingHrApproval();
    }

    public function approveByManager(User $user, LeaveRequest $leaveRequest, ?string $reviewNotes = null): void
    {
        $leaveRequest->update([
            'status' => LeaveRequestStatus::PendingHr,
            'manager_reviewed_by_employee_id' => $user->employee?->id,
            'manager_reviewed_at' => now(),
            'manager_review_notes' => $reviewNotes,
        ]);
    }

    public function approveByHr(User $user, LeaveRequest $leaveRequest, ?string $reviewNotes = null): void
    {
        $leaveRequest->update([
            'status' => LeaveRequestStatus::Approved,
            'reviewed_by_employee_id' => $user->employee?->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);
    }

    public function rejectByManager(User $user, LeaveRequest $leaveRequest, ?string $reviewNotes = null): void
    {
        $leaveRequest->update([
            'status' => LeaveRequestStatus::Rejected,
            'manager_reviewed_by_employee_id' => $user->employee?->id,
            'manager_reviewed_at' => now(),
            'manager_review_notes' => $reviewNotes,
        ]);
    }

    public function rejectByHr(User $user, LeaveRequest $leaveRequest, ?string $reviewNotes = null): void
    {
        $leaveRequest->update([
            'status' => LeaveRequestStatus::Rejected,
            'reviewed_by_employee_id' => $user->employee?->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);
    }

    public function canCancel(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $user->can('leave-requests.cancel')) {
            return false;
        }

        if (! $leaveRequest->isPending()) {
            return false;
        }

        $employee = $user->employee;

        return $employee && $leaveRequest->employee_id === $employee->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatLeaveRequest(LeaveRequest $leaveRequest): array
    {
        $this->syncApprover($leaveRequest);

        $leaveRequest->loadMissing([
            'employee.department:id,name',
            'leaveType:id,name,requires_document,is_visible_to_employees',
            'approver:id,name,staff_id',
            'managerReviewedBy:id,name,staff_id',
            'reviewedBy:id,name,staff_id',
        ]);

        $approver = $leaveRequest->isPendingManagerApproval() && $leaveRequest->employee
            ? $this->resolveApprover($leaveRequest->employee)
            : $leaveRequest->approver;

        $approverLabel = $leaveRequest->isPendingHrApproval()
            ? 'Human Resources'
            : ($approver?->name ?? null);

        return [
            'id' => $leaveRequest->id,
            'record_number' => $leaveRequest->record_number,
            'employee_id' => $leaveRequest->employee_id,
            'employee' => $leaveRequest->employee ? [
                'id' => $leaveRequest->employee->id,
                'name' => $leaveRequest->employee->name,
                'staff_id' => $leaveRequest->employee->staff_id,
                'department' => $leaveRequest->employee->department ? [
                    'id' => $leaveRequest->employee->department->id,
                    'name' => $leaveRequest->employee->department->name,
                ] : null,
            ] : null,
            'leave_type_id' => $leaveRequest->leave_type_id,
            'leave_type' => $leaveRequest->leaveType ? [
                'id' => $leaveRequest->leaveType->id,
                'name' => $leaveRequest->leaveType->name,
                'requires_document' => $leaveRequest->leaveType->requires_document,
            ] : null,
            'start_date' => $leaveRequest->start_date?->toDateString(),
            'end_date' => $leaveRequest->end_date?->toDateString(),
            'days_count' => $leaveRequest->days_count,
            'reason' => $leaveRequest->reason,
            'document_path' => $leaveRequest->document_path,
            'document_url' => $leaveRequest->document_path ? asset('storage/'.$leaveRequest->document_path) : null,
            'status' => $leaveRequest->status->value,
            'status_label' => $leaveRequest->status->label(),
            'status_color' => $leaveRequest->status->color(),
            'approver_employee_id' => $approver?->id,
            'approver' => $approver ? [
                'id' => $approver->id,
                'name' => $approver->name,
                'staff_id' => $approver->staff_id,
            ] : null,
            'approver_label' => $approverLabel,
            'manager_reviewed_by_employee_id' => $leaveRequest->manager_reviewed_by_employee_id,
            'manager_reviewed_by' => $leaveRequest->managerReviewedBy ? [
                'id' => $leaveRequest->managerReviewedBy->id,
                'name' => $leaveRequest->managerReviewedBy->name,
                'staff_id' => $leaveRequest->managerReviewedBy->staff_id,
            ] : null,
            'manager_reviewed_at' => $leaveRequest->manager_reviewed_at?->toIso8601String(),
            'manager_review_notes' => $leaveRequest->manager_review_notes,
            'reviewed_by_employee_id' => $leaveRequest->reviewed_by_employee_id,
            'reviewed_by' => $leaveRequest->reviewedBy ? [
                'id' => $leaveRequest->reviewedBy->id,
                'name' => $leaveRequest->reviewedBy->name,
                'staff_id' => $leaveRequest->reviewedBy->staff_id,
            ] : null,
            'reviewed_at' => $leaveRequest->reviewed_at?->toIso8601String(),
            'review_notes' => $leaveRequest->review_notes,
            'created_at' => $leaveRequest->created_at?->toIso8601String(),
        ];
    }
}
