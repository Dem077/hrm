<?php

namespace App\Services\Leave;

use App\Enums\ApprovalWorkflowKind;
use App\Enums\LeaveApprovalStepKey;
use App\Enums\LeaveApprovalStepStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\StructureGroupCode;
use App\Models\AppSetting;
use App\Models\ApprovalTemplate;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApprovalStep;
use App\Models\StructureNode;
use Illuminate\Validation\ValidationException;

class LeaveApprovalWorkflowService
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     enabled: bool,
     *     locked: bool,
     *     is_structure_head: bool,
     *     head_grade_ids: list<int>
     * }>
     */
    public function presentationFromSteps(?array $stepsPayload): array
    {
        $orderedKeys = [];
        $enabled = [];
        $headIdsByKey = $this->headGradeIdsByKeyFromSteps($stepsPayload);

        $rawSteps = [];
        if (is_array($stepsPayload) && isset($stepsPayload['steps']) && is_array($stepsPayload['steps'])) {
            $rawSteps = $stepsPayload['steps'];
        } elseif (is_array($stepsPayload) && array_is_list($stepsPayload)) {
            $rawSteps = $stepsPayload;
        }

        foreach ($rawSteps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $key = (string) ($step['key'] ?? '');
            $enum = LeaveApprovalStepKey::tryFrom($key);

            if (! $enum || $enum === LeaveApprovalStepKey::Hr) {
                continue;
            }

            if (in_array($key, $orderedKeys, true)) {
                continue;
            }

            $orderedKeys[] = $key;
            if ((bool) ($step['enabled'] ?? false)) {
                $enabled[] = $key;
            }
        }

        foreach (LeaveApprovalStepKey::configurableKeys() as $key) {
            if (! in_array($key->value, $orderedKeys, true)) {
                $orderedKeys[] = $key->value;
            }
        }

        if ($orderedKeys === []) {
            $orderedKeys = array_map(
                fn (LeaveApprovalStepKey $key) => $key->value,
                LeaveApprovalStepKey::configurableKeys(),
            );
            $enabled = $orderedKeys;
        }

        $steps = [];

        foreach ($orderedKeys as $keyValue) {
            $key = LeaveApprovalStepKey::from($keyValue);
            $isDirectManager = $key === LeaveApprovalStepKey::DirectManager;

            $steps[] = [
                'key' => $key->value,
                'label' => $key->label(),
                'description' => $key->description(),
                'enabled' => $isDirectManager ? true : in_array($key->value, $enabled, true),
                'locked' => $isDirectManager,
                'is_structure_head' => $key->isStructureHead(),
                'head_grade_ids' => $headIdsByKey[$key->value] ?? [],
            ];
        }

        $steps[] = [
            'key' => LeaveApprovalStepKey::Hr->value,
            'label' => LeaveApprovalStepKey::Hr->label(),
            'description' => LeaveApprovalStepKey::Hr->description(),
            'enabled' => true,
            'locked' => true,
            'is_structure_head' => false,
            'head_grade_ids' => [],
        ];

        return $steps;
    }

    /**
     * @return array{
     *     templates: list<array<string, mixed>>,
     *     company_defaults: array{leave_approval_template_id: int|null, overtime_approval_template_id: int|null},
     *     empty_steps: list<array<string, mixed>>
     * }
     */
    public function templatesPresentation(): array
    {
        $settings = AppSetting::current();

        $templates = ApprovalTemplate::query()
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ApprovalTemplate $template) => $this->formatTemplate($template))
            ->values()
            ->all();

        return [
            'templates' => $templates,
            'company_defaults' => [
                'leave_approval_template_id' => $settings->leave_approval_template_id
                    ? (int) $settings->leave_approval_template_id
                    : null,
                'overtime_approval_template_id' => $settings->overtime_approval_template_id
                    ? (int) $settings->overtime_approval_template_id
                    : null,
            ],
            'empty_steps' => $this->presentationFromSteps(['steps' => $this->defaultStepPayload()]),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     kind: string,
     *     name: string,
     *     description: string|null,
     *     steps_summary: string,
     *     steps: list<array{key: string, label: string, enabled: bool}>
     * }>
     */
    public function templateOptions(?ApprovalWorkflowKind $kind = null): array
    {
        $query = ApprovalTemplate::query()->orderBy('sort_order')->orderBy('name');

        if ($kind) {
            $query->where('kind', $kind->value);
        }

        return $query->get()
            ->map(function (ApprovalTemplate $template) {
                $presentation = $this->presentationFromSteps($this->stepsPayloadFromTemplate($template));
                $enabled = array_values(array_filter(
                    $presentation,
                    fn (array $step) => $step['enabled']
                        && $step['key'] !== LeaveApprovalStepKey::Hr->value
                        && $step['key'] !== LeaveApprovalStepKey::DirectManager->value,
                ));
                $labels = array_map(fn (array $step) => $step['label'], $enabled);
                $structure = $labels !== [] ? implode(' → ', $labels).' → HR' : 'HR only';
                $summary = 'With manager: Manager → HR · Without: '.$structure;

                return [
                    'id' => $template->id,
                    'kind' => $template->kind->value,
                    'name' => $template->name,
                    'description' => $template->description,
                    'steps_summary' => $summary,
                    'steps' => array_map(
                        fn (array $step) => [
                            'key' => $step['key'],
                            'label' => $step['label'],
                            'enabled' => (bool) $step['enabled'],
                        ],
                        array_values(array_filter(
                            $presentation,
                            fn (array $step) => $step['key'] !== LeaveApprovalStepKey::Hr->value,
                        )),
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{template: ApprovalTemplate|null, source_node: StructureNode|null, source: string}
     */
    public function resolveTemplateAssignment(Employee $employee, ApprovalWorkflowKind $kind): array
    {
        $employee->loadMissing(['grade.level.node']);
        $node = $employee->grade?->level?->node;
        $column = $kind->templateColumn();

        while ($node) {
            $node->loadMissing(['parent', $kind === ApprovalWorkflowKind::Leave ? 'leaveApprovalTemplate' : 'overtimeApprovalTemplate']);

            $templateId = $node->{$column};

            if ($templateId) {
                $template = $kind === ApprovalWorkflowKind::Leave
                    ? $node->leaveApprovalTemplate
                    : $node->overtimeApprovalTemplate;

                if ($template) {
                    return [
                        'template' => $template,
                        'source_node' => $node,
                        'source' => 'node',
                    ];
                }
            }

            $node = $node->parent_id ? $node->parent : null;
        }

        $settings = AppSetting::current();
        $defaultId = $settings->{$column};
        $template = $defaultId
            ? ApprovalTemplate::query()->where('kind', $kind->value)->find($defaultId)
            : null;

        if (! $template) {
            $template = ApprovalTemplate::query()
                ->where('kind', $kind->value)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
        }

        return [
            'template' => $template,
            'source_node' => null,
            'source' => 'company_default',
        ];
    }

    public function resolveTemplateFor(Employee $employee, ApprovalWorkflowKind $kind): ?ApprovalTemplate
    {
        return $this->resolveTemplateAssignment($employee, $kind)['template'];
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
        // Direct manager is a bypass: Manager → HR, skipping structure head steps.
        if ($this->resolveDirectManager($employee) !== null) {
            return [LeaveApprovalStepKey::DirectManager->value];
        }

        $template = $this->resolveTemplateFor($employee, $kind);
        $keys = $this->enabledKeysFromSteps($template?->steps);

        // No manager — run the template’s structure steps only (ignore direct_manager toggles).
        return array_values(array_filter(
            $keys,
            fn (string $key) => $key !== LeaveApprovalStepKey::DirectManager->value,
        ));
    }

    /**
     * @deprecated Use enabledStepKeysFor()
     *
     * @return list<string>
     */
    public function enabledStepKeys(): array
    {
        $settings = AppSetting::current();
        $template = $settings->leave_approval_template_id
            ? ApprovalTemplate::query()->find($settings->leave_approval_template_id)
            : null;

        return $this->enabledKeysFromSteps($template?->steps);
    }

    /**
     * @param  list<array{key: string, enabled: bool, head_grade_ids?: list<int>}>  $steps
     */
    public function createTemplate(ApprovalWorkflowKind $kind, string $name, ?string $description, array $steps): ApprovalTemplate
    {
        throw ValidationException::withMessages([
            'template' => 'Only the Short, Standard, and Full system templates are available for now.',
        ]);
    }

    /**
     * @param  list<array{key: string, enabled: bool, head_grade_ids?: list<int>}>  $steps
     */
    public function updateTemplate(ApprovalTemplate $template, string $name, ?string $description, array $steps): void
    {
        $template->update([
            // Keep system template names fixed.
            'name' => $template->is_system ? $template->name : $name,
            'description' => $description,
            'steps' => $this->normalizeSteps($steps),
        ]);
    }

    public function deleteTemplate(ApprovalTemplate $template): void
    {
        throw ValidationException::withMessages([
            'template' => 'Only the Short, Standard, and Full system templates are available for now.',
        ]);
    }

    public function setCompanyDefaultTemplate(ApprovalWorkflowKind $kind, ?int $templateId): void
    {
        if ($templateId !== null) {
            $exists = ApprovalTemplate::query()
                ->where('id', $templateId)
                ->where('kind', $kind->value)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    $kind->templateColumn() => 'Select a template of the correct kind.',
                ]);
            }
        }

        AppSetting::current()->update([
            $kind->templateColumn() => $templateId,
        ]);
    }

    /**
     * @deprecated Prefer templates; kept for legacy leave-workflow route.
     *
     * @param  list<array{key: string, enabled: bool, head_grade_ids?: list<int>}>  $steps
     */
    public function saveDefault(ApprovalWorkflowKind $kind, array $steps): void
    {
        $settings = AppSetting::current();
        $column = $kind->templateColumn();
        $templateId = $settings->{$column};

        $template = $templateId
            ? ApprovalTemplate::query()->where('kind', $kind->value)->find($templateId)
            : null;

        $template ??= ApprovalTemplate::query()
            ->where('kind', $kind->value)
            ->where('is_system', true)
            ->where('name', 'Full')
            ->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'template' => 'No system approval template is available to update.',
            ]);
        }

        $this->updateTemplate($template, $template->name, $template->description, $steps);
        $this->setCompanyDefaultTemplate($kind, $template->id);
    }

    /**
     * New structure nodes inherit templates (null = inherit). No-op kept for callers.
     */
    public function seedBranchWorkflows(StructureNode $branch): void
    {
        // Intentionally empty: children inherit the nearest assigned template.
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
     * Preview of the approval chain for apply forms.
     *
     * @return array{
     *     first_approver: array{id: int, name: string, staff_id: string}|null,
     *     goes_directly_to_hr: bool,
     *     template: array{id: int, name: string, source: string, source_node: array{id: int, name: string}|null}|null,
     *     steps: list<array{key: string, label: string, status: string, approver: array{id: int, name: string, staff_id: string}|null, skip_reason: string|null}>,
     *     hint: string|null
     * }
     */
    public function approvalPreview(Employee $employee, ApprovalWorkflowKind $kind = ApprovalWorkflowKind::Leave): array
    {
        $assignment = $this->resolveTemplateAssignment($employee, $kind);
        $steps = [];

        foreach ($this->planSteps($employee, $kind) as $step) {
            $approver = $step['approver'];
            $pending = $step['status'] === LeaveApprovalStepStatus::Pending && $approver instanceof Employee;

            $steps[] = [
                'key' => $step['key']->value,
                'label' => $step['label'],
                'status' => $pending ? 'pending' : 'skipped',
                'approver' => $approver instanceof Employee
                    ? [
                        'id' => $approver->id,
                        'name' => $approver->name,
                        'staff_id' => $approver->staff_id,
                    ]
                    : null,
                'skip_reason' => $pending ? null : $this->skipReason($employee, $step['key'], $kind),
            ];
        }

        $steps[] = [
            'key' => LeaveApprovalStepKey::Hr->value,
            'label' => LeaveApprovalStepKey::Hr->label(),
            'status' => 'pending',
            'approver' => null,
            'skip_reason' => null,
        ];

        $first = $this->resolveFirstApprover($employee, $kind)
            ?? $this->resolveLegacyApprover($employee);

        $goesDirectlyToHr = $first === null;
        $template = $assignment['template'];
        $sourceNode = $assignment['source_node'];

        return [
            'first_approver' => $first
                ? [
                    'id' => $first->id,
                    'name' => $first->name,
                    'staff_id' => $first->staff_id,
                ]
                : null,
            'goes_directly_to_hr' => $goesDirectlyToHr,
            'template' => $template
                ? [
                    'id' => $template->id,
                    'name' => $template->name,
                    'source' => $assignment['source'],
                    'source_node' => $sourceNode
                        ? ['id' => $sourceNode->id, 'name' => $sourceNode->name]
                        : null,
                ]
                : null,
            'steps' => $steps,
            'hint' => $goesDirectlyToHr
                ? 'Assign a direct manager (Manager → HR bypass), set head grades on company structure nodes, or adjust the approval template under Attendance settings → Approvals.'
                : null,
        ];
    }

    public function skipReason(
        Employee $employee,
        LeaveApprovalStepKey $key,
        ApprovalWorkflowKind $kind = ApprovalWorkflowKind::Leave,
    ): string {
        if ($key === LeaveApprovalStepKey::DirectManager) {
            if (! $employee->manager_id) {
                return 'No direct manager is assigned on the employee profile.';
            }

            $manager = $employee->manager ?? Employee::query()->find($employee->manager_id);

            if (! $manager) {
                return 'Assigned direct manager record was not found.';
            }

            if ($manager->id === $employee->id) {
                return 'Employee cannot be their own direct manager.';
            }

            if (! $manager->is_active) {
                return "Direct manager {$manager->name} is inactive.";
            }

            return 'Direct manager could not be used.';
        }

        $groupCode = $key->structureGroupCode();

        if (! $groupCode) {
            return 'This approval step is not configured.';
        }

        if (! $employee->grade?->level?->node) {
            return 'Employee has no company structure grade assigned.';
        }

        $filter = $this->workflowHeadGradeIdsFor($employee, $key, $kind);
        $node = $employee->grade->level->node;
        $sawMatchingGroup = false;
        $emptyHeadNode = null;
        $unfilledHeadNode = null;

        while ($node) {
            $node->loadMissing(['group', 'headGrades', 'parent']);

            if ($node->group?->code === $groupCode) {
                $sawMatchingGroup = true;
                $headGradeIds = $this->headGradeIdsForNode($node, $filter);

                if ($headGradeIds === []) {
                    $emptyHeadNode ??= $node;
                    $node = $node->parent;

                    continue;
                }

                if ($this->findHeadEmployee($headGradeIds, $node, $employee->id)) {
                    return 'Approver is available.';
                }

                $unfilledHeadNode ??= $node;
            }

            $node = $node->parent;
        }

        $branch = $this->resolveRootBranch($employee) ?? $employee->grade->level->node;
        $branch->loadMissing(['children']);
        $fallbackIds = $filter !== []
            ? $filter
            : $this->structureHeadGradeIdsForBranch($branch, $groupCode);

        if ($fallbackIds !== [] && $this->findHeadEmployee($fallbackIds, $branch, $employee->id)) {
            return 'Approver is available.';
        }

        if ($unfilledHeadNode) {
            return "No active employee holds the head grade(s) set on {$unfilledHeadNode->name}.";
        }

        if ($emptyHeadNode) {
            return "No head grade is set on {$emptyHeadNode->name} in company structure.";
        }

        if (! $sawMatchingGroup) {
            return 'Employee is not under a '.$groupCode->label().' in the company structure.';
        }

        return 'No '.$groupCode->label().' head could be resolved.';
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
            $approver = $this->resolveStepApprover($employee, $key, $kind);

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

    public function resolveStepApprover(
        Employee $employee,
        LeaveApprovalStepKey $key,
        ApprovalWorkflowKind $kind = ApprovalWorkflowKind::Leave,
    ): ?Employee {
        if ($key === LeaveApprovalStepKey::DirectManager) {
            return $this->resolveDirectManager($employee);
        }

        if (! $key->isStructureHead()) {
            return null;
        }

        return $this->resolveStructureHead($employee, $key, $kind);
    }

    public function resolveDirectManager(Employee $employee): ?Employee
    {
        if (! $employee->manager_id) {
            return null;
        }

        $manager = $employee->manager ?? Employee::query()->find($employee->manager_id);

        if (! $manager || $manager->id === $employee->id || ! $manager->is_active) {
            return null;
        }

        return $manager;
    }

    public function resolveStructureHead(
        Employee $employee,
        LeaveApprovalStepKey $key,
        ApprovalWorkflowKind $kind = ApprovalWorkflowKind::Leave,
    ): ?Employee {
        $groupCode = $key->structureGroupCode();

        if (! $groupCode) {
            return null;
        }

        $employee->loadMissing(['grade.level.node']);

        $startNode = $employee->grade?->level?->node;

        if (! $startNode) {
            return null;
        }

        $filter = $this->workflowHeadGradeIdsFor($employee, $key, $kind);
        $node = $startNode;

        while ($node) {
            $node->loadMissing(['group', 'headGrades', 'parent']);

            if ($node->group?->code === $groupCode) {
                $headGradeIds = $this->headGradeIdsForNode($node, $filter);
                $head = $this->findHeadEmployee($headGradeIds, $node, $employee->id);

                if ($head) {
                    return $head;
                }
            }

            $node = $node->parent;
        }

        $scope = $this->resolveRootBranch($employee) ?? $startNode;
        $fallbackIds = $filter !== []
            ? $filter
            : $this->structureHeadGradeIdsForBranch($scope, $groupCode);

        return $this->findHeadEmployee($fallbackIds, $scope, $employee->id);
    }

    /**
     * Optional template filter for which head designations may approve this step.
     *
     * @return list<int>
     */
    public function workflowHeadGradeIdsFor(
        Employee $employee,
        LeaveApprovalStepKey $key,
        ApprovalWorkflowKind $kind,
    ): array {
        $template = $this->resolveTemplateFor($employee, $kind);

        return $this->headGradeIdsFromSteps($template?->steps, $key);
    }

    /**
     * @return list<array{id: int, label: string, context_label: string|null}>
     */
    public function structureHeadGradeOptions(?StructureNode $branch, StructureGroupCode $groupCode): array
    {
        $query = StructureNode::query()
            ->with(['headGrades', 'group'])
            ->whereHas('group', fn ($q) => $q->where('code', $groupCode->value))
            ->whereHas('headGrades');

        if ($branch) {
            $query->whereIn('id', $this->nodeSubtreeIds($branch));
        }

        $byGrade = [];

        foreach ($query->get() as $structureNode) {
            foreach ($structureNode->headGrades as $grade) {
                $id = (int) $grade->id;

                if (! isset($byGrade[$id])) {
                    $byGrade[$id] = [
                        'id' => $id,
                        'label' => $grade->label(),
                        'node_names' => [],
                    ];
                }

                $byGrade[$id]['node_names'][] = $structureNode->name;
            }
        }

        return array_values(array_map(function (array $row) {
            $names = array_values(array_unique($row['node_names']));

            return [
                'id' => $row['id'],
                'label' => $row['label'],
                'context_label' => $names !== [] ? 'Head of '.implode(', ', $names) : null,
            ];
        }, $byGrade));
    }

    /**
     * @return list<int>
     */
    public function structureHeadGradeIdsForBranch(?StructureNode $branch, StructureGroupCode $groupCode): array
    {
        return array_map(
            fn (array $option) => $option['id'],
            $this->structureHeadGradeOptions($branch, $groupCode),
        );
    }

    /**
     * @param  list<int>  $headGradeIds
     */
    protected function findHeadEmployee(array $headGradeIds, StructureNode $scopeNode, int $excludeEmployeeId): ?Employee
    {
        if ($headGradeIds === []) {
            return null;
        }

        $scopeNodeIds = $this->nodeSubtreeIds($scopeNode);

        return Employee::query()
            ->whereIn('grade_id', $headGradeIds)
            ->where('is_active', true)
            ->where('id', '!=', $excludeEmployeeId)
            ->where(function ($query) use ($scopeNodeIds): void {
                $query
                    ->whereHas(
                        'grade.level',
                        fn ($levelQuery) => $levelQuery->whereIn('structure_node_id', $scopeNodeIds),
                    )
                    ->orWhereHas(
                        'grade.level',
                        fn ($levelQuery) => $levelQuery->whereNull('structure_node_id'),
                    );
            })
            ->orderBy('name')
            ->first();
    }

    /**
     * @param  list<int>  $filter
     * @return list<int>
     */
    protected function headGradeIdsForNode(StructureNode $node, array $filter): array
    {
        $nodeIds = $node->headGrades
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($filter === []) {
            return $nodeIds;
        }

        return array_values(array_intersect($nodeIds, $filter));
    }

    /**
     * @return list<int>
     */
    protected function nodeSubtreeIds(StructureNode $root): array
    {
        $ids = [$root->id];
        $frontier = [$root->id];

        while ($frontier !== []) {
            $children = StructureNode::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            $frontier = $children;
            $ids = array_merge($ids, $children);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatTemplate(ApprovalTemplate $template): array
    {
        $stepsPayload = $this->stepsPayloadFromTemplate($template);

        return [
            'id' => $template->id,
            'kind' => $template->kind->value,
            'name' => $template->name,
            'description' => $template->description,
            'is_system' => $template->is_system,
            'in_use' => $template->isInUse(),
            'sort_order' => $template->sort_order,
            'steps' => $this->presentationFromSteps($stepsPayload),
        ];
    }

    /**
     * @return array{steps: list<array<string, mixed>>}
     */
    protected function stepsPayloadFromTemplate(ApprovalTemplate $template): array
    {
        $steps = $template->steps;

        if (isset($steps['steps']) && is_array($steps['steps'])) {
            return ['steps' => $steps['steps']];
        }

        if (is_array($steps) && array_is_list($steps)) {
            return ['steps' => $steps];
        }

        return ['steps' => $this->defaultStepPayload()];
    }

    /**
     * @return list<int>
     */
    protected function headGradeIdsFromSteps(mixed $steps, LeaveApprovalStepKey $key): array
    {
        $payload = is_array($steps) && isset($steps['steps']) ? $steps : ['steps' => is_array($steps) ? $steps : []];

        return $this->headGradeIdsByKeyFromSteps($payload)[$key->value] ?? [];
    }

    /**
     * @return array<string, list<int>>
     */
    protected function headGradeIdsByKeyFromSteps(mixed $stepsPayload): array
    {
        $steps = [];

        if (is_array($stepsPayload) && isset($stepsPayload['steps']) && is_array($stepsPayload['steps'])) {
            $steps = $stepsPayload['steps'];
        } elseif (is_array($stepsPayload) && array_is_list($stepsPayload)) {
            $steps = $stepsPayload;
        }

        $map = [];

        foreach ($steps as $step) {
            if (! is_array($step)) {
                continue;
            }

            $key = (string) ($step['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $map[$key] = $this->normalizeIdList($step['head_grade_ids'] ?? []);
        }

        return $map;
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    protected function normalizeIdList(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id) => $id > 0,
        )));
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

        $approver = $this->resolveStepApprover($leaveRequest->employee, $step->step_key, ApprovalWorkflowKind::Leave);

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

    public function resolveLegacyApprover(Employee $employee): ?Employee
    {
        $employee->loadMissing([
            'manager',
            'grade.level.node.headGrades',
            'grade.level.node.parent.headGrades',
        ]);

        $manager = $this->resolveDirectManager($employee);

        if ($manager) {
            return $manager;
        }

        foreach ([
            LeaveApprovalStepKey::UnitSection,
            LeaveApprovalStepKey::Department,
            LeaveApprovalStepKey::Division,
        ] as $key) {
            $head = $this->resolveStructureHead($employee, $key, ApprovalWorkflowKind::Leave);

            if ($head) {
                return $head;
            }
        }

        return null;
    }

    /**
     * @param  list<array{key: string, enabled: bool, head_grade_ids?: list<int>}>  $steps
     * @return list<array{key: string, enabled: bool, head_grade_ids: list<int>}>
     */
    public function normalizeSteps(array $steps): array
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

            // Direct manager is a runtime bypass, not a stored template preference.
            if ($enum === LeaveApprovalStepKey::DirectManager) {
                $normalized[] = [
                    'key' => $key,
                    'enabled' => true,
                    'head_grade_ids' => [],
                ];

                continue;
            }

            $normalized[] = [
                'key' => $key,
                'enabled' => (bool) ($step['enabled'] ?? false),
                'head_grade_ids' => $enum->isStructureHead()
                    ? $this->normalizeIdList($step['head_grade_ids'] ?? [])
                    : [],
            ];
        }

        foreach (LeaveApprovalStepKey::configurableKeys() as $key) {
            if (isset($seen[$key->value])) {
                continue;
            }

            $normalized[] = [
                'key' => $key->value,
                'enabled' => $key === LeaveApprovalStepKey::DirectManager,
                'head_grade_ids' => [],
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{key: string, enabled: bool, head_grade_ids: list<int>}>
     */
    protected function defaultStepPayload(): array
    {
        return array_map(
            fn (LeaveApprovalStepKey $key) => [
                'key' => $key->value,
                'enabled' => true,
                'head_grade_ids' => [],
            ],
            LeaveApprovalStepKey::configurableKeys(),
        );
    }

    /**
     * @return list<string>
     */
    protected function enabledKeysFromSteps(mixed $stepsPayload): array
    {
        $default = array_map(
            fn (LeaveApprovalStepKey $key) => $key->value,
            LeaveApprovalStepKey::configurableKeys(),
        );

        $steps = [];

        if (is_array($stepsPayload) && isset($stepsPayload['steps']) && is_array($stepsPayload['steps'])) {
            $steps = $stepsPayload['steps'];
        } elseif (is_array($stepsPayload) && array_is_list($stepsPayload)) {
            $steps = $stepsPayload;
        }

        if ($steps === []) {
            return $default;
        }

        $keys = [];

        foreach ($steps as $step) {
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
