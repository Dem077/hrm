<?php

namespace App\Services\Leave;

use App\Enums\ApprovalWorkflowKind;
use App\Enums\LeaveApprovalStepKey;
use App\Enums\LeaveApprovalStepStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\StructureGroupCode;
use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApprovalStep;
use App\Models\StructureNode;
use Illuminate\Validation\ValidationException;

class LeaveApprovalWorkflowService
{
    /**
     * @return list<array{key: string, label: string, description: string, enabled: bool, locked: bool}>
     */
    public function presentation(?array $stored = null): array
    {
        $enabled = $this->enabledKeysFromStored($stored);

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
     * Company default + one config per top-level branch (under Strategic Leadership).
     *
     * @return array{
     *     default: array{id: null, name: string, description: string, leave: list<array<string, mixed>>, overtime: list<array<string, mixed>>},
     *     branches: list<array{id: int, name: string, group_label: string|null, leave: list<array<string, mixed>>, overtime: list<array<string, mixed>>}>
     * }
     */
    public function branchWorkflowsPresentation(): array
    {
        $settings = AppSetting::current();

        $branches = StructureNode::query()
            ->with('group:id,name,code')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'structure_group_id', 'leave_approval_workflow', 'overtime_approval_workflow'])
            ->map(fn (StructureNode $node) => [
                'id' => $node->id,
                'name' => $node->name,
                'group_label' => $node->group?->name,
                'leave' => $this->presentation($node->leave_approval_workflow),
                'overtime' => $this->presentation($node->overtime_approval_workflow),
            ])
            ->values()
            ->all();

        return [
            'default' => [
                'id' => null,
                'name' => 'Company default',
                'description' => 'Used for Strategic Leadership staff (no branch) and as a fallback when a branch has no workflow saved.',
                'leave' => $this->presentation($settings->leave_approval_workflow),
                'overtime' => $this->presentation($settings->overtime_approval_workflow),
            ],
            'branches' => $branches,
        ];
    }

    /**
     * Top-level branch under Strategic Leadership for this employee, if any.
     */
    public function resolveRootBranch(Employee $employee): ?StructureNode
    {
        $employee->loadMissing(['grade.level.node.parent.parent.parent']);

        $node = $employee->grade?->level?->node;

        if (! $node) {
            return null;
        }

        while ($node->parent_id) {
            $node->loadMissing('parent');
            $node = $node->parent;

            if (! $node) {
                return null;
            }
        }

        return $node;
    }

    /**
     * @return list<string>
     */
    public function enabledStepKeysFor(Employee $employee, ApprovalWorkflowKind $kind): array
    {
        $branch = $this->resolveRootBranch($employee);
        $column = $kind->column();

        if ($branch) {
            $stored = $branch->{$column};

            if ($this->hasConfiguredSteps($stored)) {
                return $this->enabledKeysFromStored($stored);
            }
        }

        $settings = AppSetting::current();

        return $this->enabledKeysFromStored($settings->{$column});
    }

    /**
     * @deprecated Use enabledStepKeysFor()
     *
     * @return list<string>
     */
    public function enabledStepKeys(): array
    {
        return $this->enabledKeysFromStored(AppSetting::current()->leave_approval_workflow);
    }

    /**
     * @param  list<array{key: string, enabled: bool}>  $steps
     */
    public function saveDefault(ApprovalWorkflowKind $kind, array $steps): void
    {
        AppSetting::current()->update([
            $kind->column() => ['steps' => $this->normalizeSteps($steps)],
        ]);
    }

    /**
     * @param  list<array{key: string, enabled: bool}>  $steps
     */
    public function saveBranch(StructureNode $branch, ApprovalWorkflowKind $kind, array $steps): void
    {
        if ($branch->parent_id !== null) {
            throw ValidationException::withMessages([
                'structure_node_id' => 'Approval workflows can only be set on branches directly under Strategic Leadership.',
            ]);
        }

        $branch->update([
            $kind->column() => ['steps' => $this->normalizeSteps($steps)],
        ]);
    }

    /**
     * @param  list<array{key: string, enabled: bool}>  $steps
     *
     * @deprecated Use saveDefault(ApprovalWorkflowKind::Leave, $steps)
     */
    public function save(array $steps): void
    {
        $this->saveDefault(ApprovalWorkflowKind::Leave, $steps);
    }

    /**
     * Copy company defaults onto a newly created top-level branch.
     */
    public function seedBranchWorkflows(StructureNode $branch): void
    {
        if ($branch->parent_id !== null) {
            return;
        }

        $settings = AppSetting::current();

        $branch->update([
            'leave_approval_workflow' => $settings->leave_approval_workflow
                ?? ['steps' => $this->defaultStepPayload()],
            'overtime_approval_workflow' => $settings->overtime_approval_workflow
                ?? ['steps' => $this->defaultStepPayload()],
        ]);
    }

    public function resolveFirstApprover(Employee $employee, ApprovalWorkflowKind $kind = ApprovalWorkflowKind::Leave): ?Employee
    {
        foreach ($this->planSteps($employee, $kind) as $step) {
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

        $plan = $this->planSteps($employee, ApprovalWorkflowKind::Leave);
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
            'status' => LeaveApprovalStepStatus::Pending->value,
        ]);

        return $firstApprover;
    }

    /**
     * @return list<array{key: LeaveApprovalStepKey, label: string, approver: ?Employee, status: LeaveApprovalStepStatus}>
     */
    public function planSteps(Employee $employee, ApprovalWorkflowKind $kind = ApprovalWorkflowKind::Leave): array
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

        foreach ($this->enabledStepKeysFor($employee, $kind) as $keyValue) {
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

    /**
     * @param  list<array{key: string, enabled: bool}>  $steps
     * @return list<array{key: string, enabled: bool}>
     */
    protected function normalizeSteps(array $steps): array
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

        return $normalized;
    }

    /**
     * @return list<array{key: string, enabled: bool}>
     */
    protected function defaultStepPayload(): array
    {
        return array_map(
            fn (LeaveApprovalStepKey $key) => ['key' => $key->value, 'enabled' => true],
            LeaveApprovalStepKey::configurableKeys(),
        );
    }

    protected function hasConfiguredSteps(mixed $stored): bool
    {
        return is_array($stored)
            && isset($stored['steps'])
            && is_array($stored['steps'])
            && $stored['steps'] !== [];
    }

    /**
     * @return list<string>
     */
    protected function enabledKeysFromStored(mixed $stored): array
    {
        $default = array_map(
            fn (LeaveApprovalStepKey $key) => $key->value,
            LeaveApprovalStepKey::configurableKeys(),
        );

        if (! $this->hasConfiguredSteps($stored)) {
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

        return $keys;
    }
}
