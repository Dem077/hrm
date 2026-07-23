<?php

namespace App\Services\Overtime;

use App\Enums\LeaveApprovalStepKey;
use App\Enums\LeaveApprovalStepStatus;
use App\Enums\OvertimeRequestStatus;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\OvertimeRequestApprovalStep;
use App\Services\Leave\LeaveApprovalWorkflowService;

/**
 * Reuses the configured leave approval workflow for overtime requests.
 */
class OvertimeApprovalWorkflowService
{
    public function __construct(
        private readonly LeaveApprovalWorkflowService $leaveApprovalWorkflowService,
    ) {}

    public function resolveFirstApprover(Employee $employee): ?Employee
    {
        return $this->leaveApprovalWorkflowService->resolveFirstApprover($employee);
    }

    public function resolveLegacyApprover(Employee $employee): ?Employee
    {
        return $this->leaveApprovalWorkflowService->resolveLegacyApprover($employee);
    }

    public function initializeSteps(OvertimeRequest $overtimeRequest, Employee $employee): ?Employee
    {
        $overtimeRequest->approvalSteps()->delete();

        $plan = $this->leaveApprovalWorkflowService->planSteps($employee);
        $order = 1;
        $firstApprover = null;

        foreach ($plan as $step) {
            OvertimeRequestApprovalStep::query()->create([
                'overtime_request_id' => $overtimeRequest->id,
                'step_order' => $order,
                'step_key' => $step['key']->value,
                'label' => $step['label'],
                'approver_employee_id' => $step['approver']?->id,
                'status' => $step['status']->value,
            ]);

            if ($firstApprover === null
                && $step['status'] === LeaveApprovalStepStatus::Pending
                && $step['key'] !== LeaveApprovalStepKey::Hr
                && $step['approver'] instanceof Employee
            ) {
                $firstApprover = $step['approver'];
            }

            $order++;
        }

        OvertimeRequestApprovalStep::query()->create([
            'overtime_request_id' => $overtimeRequest->id,
            'step_order' => $order,
            'step_key' => LeaveApprovalStepKey::Hr->value,
            'label' => LeaveApprovalStepKey::Hr->label(),
            'approver_employee_id' => null,
            'status' => LeaveApprovalStepStatus::Pending->value,
        ]);

        return $firstApprover;
    }

    public function currentPendingStructureStep(OvertimeRequest $overtimeRequest): ?OvertimeRequestApprovalStep
    {
        return $overtimeRequest->approvalSteps()
            ->where('status', LeaveApprovalStepStatus::Pending)
            ->where('step_key', '!=', LeaveApprovalStepKey::Hr->value)
            ->orderBy('step_order')
            ->first();
    }

    public function syncCurrentApprover(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        if (! $overtimeRequest->isPendingManagerApproval()) {
            return $overtimeRequest;
        }

        $overtimeRequest->loadMissing(['employee', 'approvalSteps']);

        if (! $overtimeRequest->employee) {
            return $overtimeRequest;
        }

        $step = $this->currentPendingStructureStep($overtimeRequest);

        if (! $step) {
            if ($overtimeRequest->approver_employee_id !== null) {
                $overtimeRequest->update(['approver_employee_id' => null]);
            }

            return $overtimeRequest;
        }

        $approver = $this->leaveApprovalWorkflowService->resolveStepApprover(
            $overtimeRequest->employee,
            $step->step_key,
        );

        if ($approver === null) {
            $step->update(['status' => LeaveApprovalStepStatus::Skipped, 'approver_employee_id' => null]);
            $this->advanceAfterStructureAction($overtimeRequest);

            return $overtimeRequest->fresh(['approver', 'approvalSteps']) ?? $overtimeRequest;
        }

        if ((int) $step->approver_employee_id !== (int) $approver->id) {
            $step->update(['approver_employee_id' => $approver->id]);
        }

        if ((int) $overtimeRequest->approver_employee_id !== (int) $approver->id) {
            $overtimeRequest->update(['approver_employee_id' => $approver->id]);
            $overtimeRequest->setRelation('approver', $approver);
        }

        return $overtimeRequest;
    }

    public function approveCurrentStructureStep(OvertimeRequest $overtimeRequest, Employee $actor, ?string $notes = null): void
    {
        $step = $this->currentPendingStructureStep($overtimeRequest);

        if (! $step) {
            $overtimeRequest->update([
                'status' => OvertimeRequestStatus::PendingHr,
                'approver_employee_id' => null,
                'manager_reviewed_by_employee_id' => $actor->id,
                'manager_reviewed_at' => now(),
                'manager_review_notes' => $notes,
            ]);

            return;
        }

        $step->update([
            'status' => LeaveApprovalStepStatus::Approved,
            'acted_by_employee_id' => $actor->id,
            'acted_at' => now(),
            'notes' => $notes,
            'approver_employee_id' => $step->approver_employee_id ?: $actor->id,
        ]);

        $overtimeRequest->update([
            'manager_reviewed_by_employee_id' => $actor->id,
            'manager_reviewed_at' => now(),
            'manager_review_notes' => $notes,
        ]);

        $this->advanceAfterStructureAction($overtimeRequest);
    }

    public function rejectCurrentStructureStep(OvertimeRequest $overtimeRequest, Employee $actor, ?string $notes = null): void
    {
        $step = $this->currentPendingStructureStep($overtimeRequest);

        if ($step) {
            $step->update([
                'status' => LeaveApprovalStepStatus::Rejected,
                'acted_by_employee_id' => $actor->id,
                'acted_at' => now(),
                'notes' => $notes,
                'approver_employee_id' => $step->approver_employee_id ?: $actor->id,
            ]);
        }

        $overtimeRequest->approvalSteps()
            ->where('status', LeaveApprovalStepStatus::Pending)
            ->update(['status' => LeaveApprovalStepStatus::Skipped]);

        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::Rejected,
            'approver_employee_id' => null,
            'manager_reviewed_by_employee_id' => $actor->id,
            'manager_reviewed_at' => now(),
            'manager_review_notes' => $notes,
        ]);
    }

    public function markHrStep(OvertimeRequest $overtimeRequest, LeaveApprovalStepStatus $status, ?Employee $actor, ?string $notes = null): void
    {
        $hrStep = $overtimeRequest->approvalSteps()
            ->where('step_key', LeaveApprovalStepKey::Hr->value)
            ->first();

        if ($hrStep) {
            $hrStep->update([
                'status' => $status,
                'acted_by_employee_id' => $actor?->id,
                'acted_at' => now(),
                'notes' => $notes,
            ]);
        }
    }

    protected function advanceAfterStructureAction(OvertimeRequest $overtimeRequest): void
    {
        $overtimeRequest->refresh();

        $next = $this->currentPendingStructureStep($overtimeRequest);

        while ($next && ! $next->approver_employee_id) {
            $next->update(['status' => LeaveApprovalStepStatus::Skipped]);
            $next = $this->currentPendingStructureStep($overtimeRequest->fresh() ?? $overtimeRequest);
        }

        if ($next && $next->approver_employee_id) {
            $overtimeRequest->update([
                'status' => OvertimeRequestStatus::Pending,
                'approver_employee_id' => $next->approver_employee_id,
            ]);

            return;
        }

        $overtimeRequest->update([
            'status' => OvertimeRequestStatus::PendingHr,
            'approver_employee_id' => null,
        ]);
    }
}
