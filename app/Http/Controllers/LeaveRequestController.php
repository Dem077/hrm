<?php

namespace App\Http\Controllers;

use App\Enums\LeaveRequestStatus;
use App\Http\Requests\ReviewLeaveRequestRequest;
use App\Http\Requests\StoreLeaveForStaffRequest;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request, LeaveRequestService $leaveService): Response
    {
        $leaveService->syncAllPendingApprovers();

        $filter = $request->string('filter', 'mine')->toString();
        $employee = $request->user()?->employee;

        $query = $leaveService->accessibleRequestsQuery($request->user());

        if ($filter === 'mine' && $employee) {
            $query->where('employee_id', $employee->id);
        } elseif ($filter === 'pending-approval' && $employee) {
            $leaveService->applyPendingManagerApproverScope($query, $employee);
        } elseif ($filter === 'pending-hr') {
            $query->where('status', LeaveRequestStatus::PendingHr);
        } elseif ($filter === 'pending') {
            $query->whereIn('status', [LeaveRequestStatus::Pending, LeaveRequestStatus::PendingHr]);
        }

        $requests = $query->limit(200)->get()
            ->map(fn (LeaveRequest $leaveRequest) => $leaveService->formatLeaveRequest($leaveRequest));

        return Inertia::render('LeaveRequests/Index', [
            'requests' => $requests,
            'filters' => [
                'filter' => $filter,
            ],
            'canCreate' => $request->user()?->can('leave-requests.create') ?? false,
            'canRecordForOthers' => $request->user()?->can('leave-requests.record-for-others') ?? false,
            'canViewAll' => $request->user()?->can('leave-requests.view-all') ?? false,
            'hasEmployeeProfile' => $employee !== null,
            'pendingApprovalCount' => $employee
                ? $leaveService->pendingManagerApprovalCount($employee)
                : 0,
            'pendingHrApprovalCount' => LeaveRequest::query()
                ->where('status', LeaveRequestStatus::PendingHr)
                ->count(),
        ]);
    }

    public function create(LeaveRequestService $leaveService): Response|RedirectResponse
    {
        $employee = request()->user()?->employee;

        if (! $employee) {
            return redirect()
                ->route('leave-requests.index')
                ->with('error', 'Your login account must be linked to an employee record before applying for leave.');
        }

        $leaveTypes = $leaveService->visibleLeaveTypesQuery()
            ->get(['id', 'name', 'description', 'requires_document', 'annual_limit'])
            ->map(fn (LeaveType $type) => $leaveService->formatLeaveTypeOption($employee, $type));

        if ($leaveTypes->isEmpty()) {
            return redirect()
                ->route('leave-requests.index')
                ->with('error', 'No leave types are available for employees right now.');
        }

        $approver = $leaveService->resolveApprover($employee);
        $approvalPreview = app(\App\Services\Leave\LeaveApprovalWorkflowService::class)
            ->approvalPreview($employee);

        return Inertia::render('LeaveRequests/Form', [
            'leaveTypes' => $leaveTypes,
            'approver' => $approver ? [
                'id' => $approver->id,
                'name' => $approver->name,
                'staff_id' => $approver->staff_id,
            ] : null,
            'approvalPreview' => $approvalPreview,
            'request' => [
                'leave_type_id' => '',
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'reason' => '',
            ],
        ]);
    }

    public function store(StoreLeaveRequestRequest $request, LeaveRequestService $leaveService): RedirectResponse
    {
        $employee = $request->user()?->employee;

        if (! $employee) {
            return back()->with('error', 'Your login account is not linked to an employee record.');
        }

        $timezone = config('app.timezone', 'UTC');
        $startDate = Carbon::parse($request->validated('start_date'), $timezone)->startOfDay();
        $endDate = Carbon::parse($request->validated('end_date'), $timezone)->startOfDay();
        $documentPath = null;

        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leave-documents', 'public');
        }

        $leaveRequest = LeaveRequest::query()->create([
            'record_number' => $leaveService->generateRecordNumber(),
            'employee_id' => $employee->id,
            'leave_type_id' => $request->integer('leave_type_id'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_count' => $leaveService->calculateDaysCount($startDate, $endDate),
            'reason' => $request->string('reason')->toString(),
            'document_path' => $documentPath,
            'status' => LeaveRequestStatus::Pending,
            'approver_employee_id' => null,
        ]);

        $firstApprover = app(\App\Services\Leave\LeaveApprovalWorkflowService::class)
            ->initializeSteps($leaveRequest, $employee);

        if ($firstApprover) {
            $leaveRequest->update([
                'status' => LeaveRequestStatus::Pending,
                'approver_employee_id' => $firstApprover->id,
            ]);
            $successMessage = 'Leave request submitted. It will follow the company structure approval workflow, then HR.';
        } else {
            $leaveRequest->update([
                'status' => LeaveRequestStatus::PendingHr,
                'approver_employee_id' => null,
            ]);
            $successMessage = 'Leave request submitted and sent directly to HR for final approval.';
        }

        return redirect()
            ->route('leave-requests.show', $leaveRequest)
            ->with('success', $successMessage);
    }

    public function checkPunches(Request $request, LeaveRequestService $leaveService): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $user = $request->user();
        $canRecordForOthers = $user?->can('leave-requests.record-for-others') ?? false;

        if (! empty($validated['employee_id'])) {
            abort_unless($canRecordForOthers, 403);
            $employeeId = (int) $validated['employee_id'];
        } else {
            abort_unless($user?->can('leave-requests.create') ?? false, 403);
            $employeeId = $user?->employee?->id;

            abort_if(! $employeeId, 422, 'Your login account is not linked to an employee record.');
        }

        $timezone = config('app.timezone', 'UTC');
        $startDate = Carbon::parse($validated['start_date'], $timezone)->startOfDay();
        $endDate = Carbon::parse($validated['end_date'], $timezone)->startOfDay();

        return response()->json($leaveService->punchConflictSummary($employeeId, $startDate, $endDate));
    }

    public function annualBalance(Request $request, LeaveRequestService $leaveService): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['nullable', 'date'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $user = $request->user();
        $canRecordForOthers = $user?->can('leave-requests.record-for-others') ?? false;

        if (! empty($validated['employee_id'])) {
            abort_unless($canRecordForOthers, 403);
            $employee = Employee::query()->findOrFail((int) $validated['employee_id']);
        } else {
            abort_unless($user?->can('leave-requests.create') ?? false, 403);
            $employee = $user?->employee;

            abort_if(! $employee, 422, 'Your login account is not linked to an employee record.');
        }

        $leaveType = LeaveType::query()->findOrFail((int) $validated['leave_type_id']);
        $timezone = config('app.timezone', 'UTC');
        $referenceDate = ! empty($validated['start_date'])
            ? Carbon::parse($validated['start_date'], $timezone)->startOfDay()
            : now($timezone)->startOfDay();

        return response()->json($leaveService->leaveBalanceSummary($employee, $leaveType, $referenceDate));
    }

    public function createForStaff(LeaveRequestService $leaveService): Response|RedirectResponse
    {
        $leaveTypes = $leaveService->visibleLeaveTypesQuery(forEmployees: false)
            ->get(['id', 'name', 'description', 'requires_document', 'annual_limit'])
            ->map(fn (LeaveType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'requires_document' => $type->requires_document,
                'annual_limit' => $type->annual_limit,
                'used_days' => null,
                'remaining_days' => null,
                'period_start' => null,
                'period_end' => null,
            ]);

        if ($leaveTypes->isEmpty()) {
            return redirect()
                ->route('leave-requests.index')
                ->with('error', 'No active leave types are available.');
        }

        return Inertia::render('LeaveRequests/Record', [
            'employees' => Employee::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'staff_id'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'label' => "{$employee->name} ({$employee->staff_id})",
                ]),
            'leaveTypes' => $leaveTypes,
            'request' => [
                'employee_id' => '',
                'leave_type_id' => '',
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'reason' => '',
                'review_notes' => '',
            ],
        ]);
    }

    public function storeForStaff(StoreLeaveForStaffRequest $request, LeaveRequestService $leaveService): RedirectResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $timezone = config('app.timezone', 'UTC');
        $startDate = Carbon::parse($request->validated('start_date'), $timezone)->startOfDay();
        $endDate = Carbon::parse($request->validated('end_date'), $timezone)->startOfDay();
        $documentPath = null;

        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leave-documents', 'public');
        }

        $leaveRequest = $leaveService->recordLeaveForEmployee(
            employee: $employee,
            leaveTypeId: $request->integer('leave_type_id'),
            startDate: $startDate,
            endDate: $endDate,
            reason: $request->string('reason')->toString(),
            documentPath: $documentPath,
            recordedBy: $request->user()?->employee,
            reviewNotes: $request->filled('review_notes') ? $request->string('review_notes')->toString() : null,
        );

        return redirect()
            ->route('leave-requests.show', $leaveRequest)
            ->with('success', 'Leave recorded and approved for '.$employee->name.'.');
    }

    public function show(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $leaveService): Response
    {
        $leaveService->syncApprover($leaveRequest);

        abort_unless($leaveService->canView($request->user(), $leaveRequest), 403);

        return Inertia::render('LeaveRequests/Show', [
            'leaveRequest' => $leaveService->formatLeaveRequest($leaveRequest),
            'canApprove' => $leaveService->canApprove($request->user(), $leaveRequest),
            'canApproveHr' => $leaveService->canApproveHr($request->user(), $leaveRequest),
            'canCancel' => $leaveService->canCancel($request->user(), $leaveRequest),
        ]);
    }

    public function approve(ReviewLeaveRequestRequest $request, LeaveRequest $leaveRequest, LeaveRequestService $leaveService): RedirectResponse
    {
        $user = $request->user();

        if ($leaveService->canApproveHr($user, $leaveRequest)) {
            $leaveService->approveByHr($user, $leaveRequest, $request->validated('review_notes'));

            return back()->with('success', 'Leave request approved.');
        }

        abort_unless($leaveService->canApprove($user, $leaveRequest), 403);

        $leaveService->approveByManager($user, $leaveRequest, $request->validated('review_notes'));

        return back()->with('success', 'Leave request approved and sent to HR for final approval.');
    }

    public function reject(ReviewLeaveRequestRequest $request, LeaveRequest $leaveRequest, LeaveRequestService $leaveService): RedirectResponse
    {
        $user = $request->user();

        if ($leaveService->canApproveHr($user, $leaveRequest)) {
            $leaveService->rejectByHr($user, $leaveRequest, $request->validated('review_notes'));

            return back()->with('success', 'Leave request rejected.');
        }

        abort_unless($leaveService->canApprove($user, $leaveRequest), 403);

        $leaveService->rejectByManager($user, $leaveRequest, $request->validated('review_notes'));

        return back()->with('success', 'Leave request rejected.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $leaveService): RedirectResponse
    {
        abort_unless($leaveService->canCancel($request->user(), $leaveRequest), 403);

        $leaveRequest->update([
            'status' => LeaveRequestStatus::Cancelled,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Leave request cancelled.');
    }
}
