<?php

namespace App\Services\Leave;

use App\Enums\LeaveApprovalStepKey;
use App\Enums\LeaveApprovalStepStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\StructureGroupCode;
use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApprovalStep;

class LeaveApprovalWorkflowService
{
    /**
     * @return list<array{key: string, label: string, description: string, enabled: bool, locked: bool}>
     */
    public function presentation(): array
    {
        $enabled = $this->enabledStepKeys();

        $steps = [];

        foreach (LeaveApprovalStepKey::configurableKeys() as $key) {
            $steps[] = [
                'key' => $key->value,
                'label' => $key->label(),
                'description' => $key->description(),
                'enabled' => in_array($key->value, $enabled, true),
                'locked' => false,
            ];
        }

        $steps[] = [
            'key' => LeaveApprovalStepKey::Hr->value,
            'label' => LeaveApprovalStepKey::Hr->label(),
            'description' => LeaveApprovalStepKey::Hr->description(),
            'enabled' => true,
            'locked' => true,
        ];

        return $steps;
    }

    /**
     * @return list<string>
     */
    public function enabledStepKeys(): array
    {
        $stored = AppSetting::current()->leave_approval_workflow;
        $default = array_map(
            fn (LeaveApprovalStepKey $key) => $key->value,
            LeaveApprovalStepKey::configurableKeys(),
        );

        if (! is_array($stored) || ! isset($stored['steps']) || ! is_array($stored['steps'])) {
            return $default;
        }

        $keys = [];

        foreach ($stored['steps'] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $key = (string) ($step['key'] ?? '');
            $enabled = (bool) ($step['enabled'] ?? false);

            if (! $enabled || $key === LeaveApprovalStepKey::Hr->value) {
                continue;
            }

            if (LeaveApprovalStepKey::tryFrom($key) === null) {
                continue;
            }

            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys === [] ? $default : $keys;
    }

    /**
     * @param  list<array{key: string, enabled: bool}>  $steps
     */
    public function save(array $steps): void
    {
        $normalized = [];
        $seen = [];

        foreach ($steps as $step) {
            $key = (string) ($step['key'] ?? '');
            $enum = LeaveApprovalStepKey::tryFrom($key);

            if (! $enum || $enum === LeaveApprovalStepKey::Hr) {
                continue;
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = [
                'key' => $key,
                'enabled' => (bool) ($step['enabled'] ?? false),
            ];
        }

        foreach (LeaveApprovalStepKey::configurableKeys() as $key) {
            if (! isset($seen[$key->value])) {
                $normalized[] = [
                    'key' => $key->value,
                    'enabled' => false,
                ];
            }
        }

        AppSetting::current()->update([
            'leave_approval_workflow' => ['steps' => $normalized],
        ]);
    }

    public function resolveFirstApprover(Employee $employee): ?Employee
    {
        foreach ($this->planSteps($employee) as $step) {
            if ($step['status'] === LeaveApprovalStepStatus::Pending && $step['approver'] instanceof Employee) {
                return $step['approver'];
            }
        }

        return null;
    }

    /**
     * Build and persist approval steps for a leave request. Returns the first pending structure approver (if any).
     */
    public function initializeSteps(LeaveRequest $leaveRequest, Employee $employee): ?Employee
    {
        $leaveRequest->approvalSteps()->delete();

        $plan = $this->planSteps($employee);
        $order = 1;
        $firstApprover = null;

        foreach ($plan as $step) {
            LeaveRequestApprovalStep::query()->create([
                'leave_request_id' => $leaveRequest->id,
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

        LeaveRequestApprovalStep::query()->create([
            'leave_request_id' => $leaveRequest->id,
            'step_order' => $order,
            'step_key' => LeaveApprovalStepKey::Hr->value,
            'label' => LeaveApprovalStepKey::Hr->label(),
            'approver_employee_id' => null,
            'status' => $firstApprover
                ? LeaveApprovalStepStatus::Pending->value
                : LeaveApprovalStepStatus::Pending->value,
        ]);

        return $firstApprover;
    }

    /**
     * @return list<array{key: LeaveApprovalStepKey, label: string, approver: ?Employee, status: LeaveApprovalStepStatus}>
     */
    public function planSteps(Employee $employee): array
    {
        $employee->loadMissing([
            'manager',
            'grade.level.node.group',
            'grade.level.node.headGrades',
            'grade.level.node.parent.group',
            'grade.level.node.parent.headGrades',
            'grade.level.node.parent.parent.group',
            'grade.level.node.parent.parent.headGrades',
        ]);

        $plan = [];

        foreach ($this->enabledStepKeys() as $keyValue) {
            $key = LeaveApprovalStepKey::from($keyValue);
            $approver = $this->resolveStepApprover($employee, $key);

            $plan[] = [
                'key' => $key,
                'label' => $key->label(),
                'approver' => $approver,
                'status' => $approver
                    ? LeaveApprovalStepStatus::Pending
                    : LeaveApprovalStepStatus::Skipped,
            ];
        }

        return $plan;
    }

    public function resolveStepApprover(Employee $employee, LeaveApprovalStepKey $key): ?Employee
    {
        if ($key === LeaveApprovalStepKey::DirectManager) {
            if (! $employee->manager_id) {
                return null;
            }

            $manager = $employee->manager ?? Employee::query()->find($employee->manager_id);

            if (! $manager || $manager->id === $employee->id || ! $manager->is_active) {
                return null;
            }

            return $manager;
        }

        $groupCode = $key->structureGroupCode();

        if (! $groupCode) {
            return null;
        }

        return $this->resolveStructureHead($employee, $groupCode);
    }

    public function resolveStructureHead(Employee $employee, StructureGroupCode $groupCode): ?Employee
    {
        $node = $employee->grade?->level?->node;

        while ($node) {
            $node->loadMissing(['group', 'headGrades', 'parent']);

            if ($node->group?->code === $groupCode) {
                $headGradeIds = $node->headGrades->pluck('id')->all();

                if ($headGradeIds === []) {
                    return null;
                }

                return Employee::query()
                    ->whereIn('grade_id', $headGradeIds)
                    ->where('is_active', true)
                    ->where('id', '!=', $employee->id)
                    ->orderBy('name')
                    ->first();
            }

            $node = $node->parent;
        }

        return null;
    }

    public function currentPendingStructureStep(LeaveRequest $leaveRequest): ?LeaveRequestApprovalStep
    {
        return $leaveRequest->approvalSteps()
            ->where('status', LeaveApprovalStepStatus::Pending)
            ->where('step_key', '!=', LeaveApprovalStepKey::Hr->value)
            ->orderBy('step_order')
            ->first();
    }

    public function syncCurrentApprover(LeaveRequest $leaveRequest): LeaveRequest
    {
        if (! $leaveRequest->isPendingManagerApproval()) {
            return $leaveRequest;
        }

        $leaveRequest->loadMissing(['employee', 'approvalSteps']);

        if (! $leaveRequest->employee) {
            return $leaveRequest;
        }

        $step = $this->currentPendingStructureStep($leaveRequest);

        if (! $step) {
            if ($leaveRequest->approver_employee_id !== null) {
                $leaveRequest->update(['approver_employee_id' => null]);
            }

            return $leaveRequest;
        }

        $approver = $this->resolveStepApprover($leaveRequest->employee, $step->step_key);

        if ($approver === null) {
            $step->update(['status' => LeaveApprovalStepStatus::Skipped, 'approver_employee_id' => null]);
            $this->advanceAfterStructureAction($leaveRequest);

            return $leaveRequest->fresh(['approver', 'approvalSteps']) ?? $leaveRequest;
        }

        if ((int) $step->approver_employee_id !== (int) $approver->id) {
            $step->update(['approver_employee_id' => $approver->id]);
        }

        if ((int) $leaveRequest->approver_employee_id !== (int) $approver->id) {
            $leaveRequest->update(['approver_employee_id' => $approver->id]);
            $leaveRequest->setRelation('approver', $approver);
        }

        return $leaveRequest;
    }

    public function approveCurrentStructureStep(LeaveRequest $leaveRequest, Employee $actor, ?string $notes = null): void
    {
        $step = $this->currentPendingStructureStep($leaveRequest);

        if (! $step) {
            $leaveRequest->update([
                'status' => LeaveRequestStatus::PendingHr,
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

        $leaveRequest->update([
            'manager_reviewed_by_employee_id' => $actor->id,
            'manager_reviewed_at' => now(),
            'manager_review_notes' => $notes,
        ]);

        $this->advanceAfterStructureAction($leaveRequest);
    }

    public function rejectCurrentStructureStep(LeaveRequest $leaveRequest, Employee $actor, ?string $notes = null): void
    {
        $step = $this->currentPendingStructureStep($leaveRequest);

        if ($step) {
            $step->update([
                'status' => LeaveApprovalStepStatus::Rejected,
                'acted_by_employee_id' => $actor->id,
                'acted_at' => now(),
                'notes' => $notes,
                'approver_employee_id' => $step->approver_employee_id ?: $actor->id,
            ]);
        }

        $leaveRequest->approvalSteps()
            ->where('status', LeaveApprovalStepStatus::Pending)
            ->update(['status' => LeaveApprovalStepStatus::Skipped]);

        $leaveRequest->update([
            'status' => LeaveRequestStatus::Rejected,
            'approver_employee_id' => null,
            'manager_reviewed_by_employee_id' => $actor->id,
            'manager_reviewed_at' => now(),
            'manager_review_notes' => $notes,
        ]);
    }

    public function markHrStep(LeaveRequest $leaveRequest, LeaveApprovalStepStatus $status, ?Employee $actor, ?string $notes = null): void
    {
        $hrStep = $leaveRequest->approvalSteps()
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

    protected function advanceAfterStructureAction(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->refresh();

        $next = $this->currentPendingStructureStep($leaveRequest);

        while ($next && ! $next->approver_employee_id) {
            $next->update(['status' => LeaveApprovalStepStatus::Skipped]);
            $next = $this->currentPendingStructureStep($leaveRequest->fresh() ?? $leaveRequest);
        }

        if ($next && $next->approver_employee_id) {
            $leaveRequest->update([
                'status' => LeaveRequestStatus::Pending,
                'approver_employee_id' => $next->approver_employee_id,
            ]);

            return;
        }

        $leaveRequest->update([
            'status' => LeaveRequestStatus::PendingHr,
            'approver_employee_id' => null,
        ]);
    }

    /**
     * Legacy fallback used when a request has no step rows yet.
     */
    public function resolveLegacyApprover(Employee $employee): ?Employee
    {
        $employee->loadMissing([
            'manager',
            'grade.level.node.headGrades',
            'grade.level.node.parent.headGrades',
        ]);

        if ($employee->manager_id) {
            $manager = $employee->manager ?? Employee::query()->find($employee->manager_id);

            if ($manager && $manager->id !== $employee->id && $manager->is_active) {
                return $manager;
            }
        }

        $node = $employee->grade?->level?->node;

        while ($node) {
            $node->loadMissing(['headGrades', 'parent']);
            $headGradeIds = $node->headGrades->pluck('id')->all();

            if ($headGradeIds !== []) {
                $head = Employee::query()
                    ->whereIn('grade_id', $headGradeIds)
                    ->where('is_active', true)
                    ->where('id', '!=', $employee->id)
                    ->orderBy('name')
                    ->first();

                if ($head) {
                    return $head;
                }
            }

            $node = $node->parent;
        }

        return null;
    }
}
