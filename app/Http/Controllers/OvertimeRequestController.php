<?php

namespace App\Http\Controllers;

use App\Enums\OvertimeRequestStatus;
use App\Http\Requests\ReviewOvertimeRequestRequest;
use App\Http\Requests\StoreOvertimeRequestRequest;
use App\Models\OvertimeRequest;
use App\Services\Overtime\OvertimeApprovalWorkflowService;
use App\Services\Overtime\OvertimeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeRequestController extends Controller
{
    public function index(Request $request, OvertimeRequestService $overtimeService): Response
    {
        $overtimeService->syncAllPendingApprovers();

        $filter = $request->string('filter', 'mine')->toString();
        $employee = $request->user()?->employee;

        $query = $overtimeService->accessibleRequestsQuery($request->user());

        if ($filter === 'mine' && $employee) {
            $query->where('employee_id', $employee->id);
        } elseif ($filter === 'pending-approval' && $employee) {
            $overtimeService->applyPendingManagerApproverScope($query, $employee);
        } elseif ($filter === 'pending-hr') {
            $query->where('status', OvertimeRequestStatus::PendingHr);
        } elseif ($filter === 'pending') {
            $query->whereIn('status', [OvertimeRequestStatus::Pending, OvertimeRequestStatus::PendingHr]);
        }

        $requests = $query->limit(200)->get()
            ->map(fn (OvertimeRequest $overtimeRequest) => $overtimeService->formatOvertimeRequest($overtimeRequest));

        return Inertia::render('OvertimeRequests/Index', [
            'requests' => $requests,
            'filters' => [
                'filter' => $filter,
            ],
            'canCreate' => $request->user()?->can('overtime-requests.create') ?? false,
            'canViewAll' => $request->user()?->can('overtime-requests.view-all') ?? false,
            'hasEmployeeProfile' => $employee !== null,
            'pendingApprovalCount' => $employee
                ? $overtimeService->pendingManagerApprovalCount($employee)
                : 0,
            'pendingHrApprovalCount' => OvertimeRequest::query()
                ->where('status', OvertimeRequestStatus::PendingHr)
                ->count(),
        ]);
    }

    public function create(OvertimeRequestService $overtimeService): Response|RedirectResponse
    {
        $employee = request()->user()?->employee;

        if (! $employee) {
            return redirect()
                ->route('overtime-requests.index')
                ->with('error', 'Your login account must be linked to an employee record before applying for overtime.');
        }

        $eligibleDays = $overtimeService->eligibleOvertimeDays($employee);

        if ($eligibleDays === []) {
            return redirect()
                ->route('overtime-requests.index')
                ->with('error', 'No claimable overtime found. You can only apply for hours worked after your duty end time.');
        }

        $approver = $overtimeService->resolveApprover($employee);
        $firstDay = $eligibleDays[0];

        return Inertia::render('OvertimeRequests/Form', [
            'approver' => $approver ? [
                'id' => $approver->id,
                'name' => $approver->name,
                'staff_id' => $approver->staff_id,
            ] : null,
            'eligibleDays' => $eligibleDays,
            'request' => [
                'overtime_date' => $firstDay['overtime_date'],
                'start_time' => $firstDay['start_time'],
                'end_time' => $firstDay['end_time'],
                'hours' => $firstDay['available_hours'],
                'reason' => '',
            ],
        ]);
    }

    public function store(
        StoreOvertimeRequestRequest $request,
        OvertimeRequestService $overtimeService,
        OvertimeApprovalWorkflowService $workflowService,
    ): RedirectResponse {
        $employee = $request->user()?->employee;

        if (! $employee) {
            return back()->with('error', 'Your login account is not linked to an employee record.');
        }

        $timezone = config('app.timezone', 'UTC');
        $overtimeDate = Carbon::parse($request->validated('overtime_date'), $timezone)->startOfDay();
        $eligibility = $overtimeService->eligibilityForDate($employee, $overtimeDate);

        if ($eligibility === null) {
            return back()->with('error', 'No claimable overtime hours after duty end were found for this date.');
        }

        $hours = min((float) $request->validated('hours'), (float) $eligibility['available_hours']);

        $overtimeRequest = OvertimeRequest::query()->create([
            'record_number' => $overtimeService->generateRecordNumber(),
            'employee_id' => $employee->id,
            'overtime_date' => $overtimeDate,
            'start_time' => $eligibility['start_time'],
            'end_time' => $eligibility['end_time'],
            'hours' => $hours,
            'reason' => $request->string('reason')->toString(),
            'status' => OvertimeRequestStatus::Pending,
            'approver_employee_id' => null,
        ]);

        $firstApprover = $workflowService->initializeSteps($overtimeRequest, $employee);

        if ($firstApprover) {
            $overtimeRequest->update([
                'status' => OvertimeRequestStatus::Pending,
                'approver_employee_id' => $firstApprover->id,
            ]);
            $successMessage = 'Overtime request submitted. It will follow the same approval workflow as leave, then HR.';
        } else {
            $overtimeRequest->update([
                'status' => OvertimeRequestStatus::PendingHr,
                'approver_employee_id' => null,
            ]);
            $successMessage = 'Overtime request submitted and sent directly to HR for final approval.';
        }

        return redirect()
            ->route('overtime-requests.show', $overtimeRequest)
            ->with('success', $successMessage);
    }

    public function show(Request $request, OvertimeRequest $overtimeRequest, OvertimeRequestService $overtimeService): Response
    {
        $overtimeService->syncApprover($overtimeRequest);

        abort_unless($overtimeService->canView($request->user(), $overtimeRequest), 403);

        return Inertia::render('OvertimeRequests/Show', [
            'overtimeRequest' => $overtimeService->formatOvertimeRequest($overtimeRequest),
            'canApprove' => $overtimeService->canApprove($request->user(), $overtimeRequest),
            'canApproveHr' => $overtimeService->canApproveHr($request->user(), $overtimeRequest),
            'canCancel' => $overtimeService->canCancel($request->user(), $overtimeRequest),
        ]);
    }

    public function approve(
        ReviewOvertimeRequestRequest $request,
        OvertimeRequest $overtimeRequest,
        OvertimeRequestService $overtimeService,
    ): RedirectResponse {
        $user = $request->user();

        if ($overtimeService->canApproveHr($user, $overtimeRequest)) {
            $overtimeService->approveByHr($user, $overtimeRequest, $request->validated('review_notes'));

            return back()->with('success', 'Overtime request approved.');
        }

        abort_unless($overtimeService->canApprove($user, $overtimeRequest), 403);

        $overtimeService->approveByManager($user, $overtimeRequest, $request->validated('review_notes'));

        return back()->with('success', 'Overtime request approved and sent to the next step or HR.');
    }

    public function reject(
        ReviewOvertimeRequestRequest $request,
        OvertimeRequest $overtimeRequest,
        OvertimeRequestService $overtimeService,
    ): RedirectResponse {
        $user = $request->user();

        if ($overtimeService->canApproveHr($user, $overtimeRequest)) {
            $overtimeService->rejectByHr($user, $overtimeRequest, $request->validated('review_notes'));

            return back()->with('success', 'Overtime request rejected.');
        }

        abort_unless($overtimeService->canApprove($user, $overtimeRequest), 403);

        $overtimeService->rejectByManager($user, $overtimeRequest, $request->validated('review_notes'));

        return back()->with('success', 'Overtime request rejected.');
    }

    public function cancel(Request $request, OvertimeRequest $overtimeRequest, OvertimeRequestService $overtimeService): RedirectResponse
    {
        abort_unless($overtimeService->canCancel($request->user(), $overtimeRequest), 403);

        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::Cancelled,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Overtime request cancelled.');
    }
}
