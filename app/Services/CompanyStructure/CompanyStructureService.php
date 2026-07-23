<?php

namespace App\Services\CompanyStructure;

use App\Enums\StructureGroupCode;
use App\Models\Employee;
use App\Models\StructureGrade;
use App\Models\StructureGroup;
use App\Models\StructureLevel;
use App\Models\StructureNode;
use App\Services\Payroll\GradePayrollService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyStructureService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function treePayload(): array
    {
        $groups = StructureGroup::query()
            ->orderBy('sort_order')
            ->with(['levels.grades'])
            ->get()
            ->keyBy(fn (StructureGroup $group) => $group->code->value);

        $allNodes = StructureNode::query()
            ->with([
                'group',
                'headGrades',
                'levels.grades',
                'leaveApprovalTemplate:id,name,kind',
                'overtimeApprovalTemplate:id,name,kind',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $nodesByParent = $allNodes->groupBy(fn (StructureNode $node) => $node->parent_id ?? 0);

        $headGradeIds = $allNodes
            ->flatMap(fn (StructureNode $node) => $node->headGrades->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $employeesByHeadGrade = $headGradeIds === []
            ? collect()
            : Employee::query()
                ->whereIn('grade_id', $headGradeIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'staff_id', 'grade_id'])
                ->groupBy('grade_id');

        $payload = [];

        $strategic = $groups->get(StructureGroupCode::StrategicLeadership->value);

        if ($strategic) {
            $payload[] = [
                'id' => $strategic->id,
                'code' => $strategic->code->value,
                'name' => $strategic->name,
                'sort_order' => $strategic->sort_order,
                'allows_nodes' => false,
                'is_org_tree' => false,
                'levels' => $strategic->levels
                    ->map(fn (StructureLevel $level) => $this->formatLevel($level))
                    ->values()
                    ->all(),
                'nodes' => [],
                'group_options' => [],
            ];
        }

        $payload[] = [
            'id' => $groups->get(StructureGroupCode::Division->value)?->id ?? 0,
            'code' => 'organization',
            'name' => 'Organization',
            'sort_order' => 1,
            'allows_nodes' => true,
            'is_org_tree' => true,
            'levels' => [],
            'nodes' => $this->formatNodeTree($nodesByParent, 0, $employeesByHeadGrade),
            'group_options' => $groups
                ->filter(fn (StructureGroup $group) => $group->allowsNodes())
                ->map(fn (StructureGroup $group) => [
                    'id' => $group->id,
                    'code' => $group->code->value,
                    'name' => $group->name,
                ])
                ->values()
                ->all(),
        ];

        return $payload;
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     path_label: string,
     *     context_label: string,
     *     group_name: string|null,
     *     group_code: string|null,
     *     group_sort: int,
     *     node_name: string|null,
     *     node_path: string|null,
     *     level_label: string|null,
     *     section_label: string,
     *     is_active: bool
     * }>
     */
    public function gradeOptions(bool $activeOnly = true): array
    {
        $groupSort = [
            StructureGroupCode::StrategicLeadership->value => 0,
            StructureGroupCode::Division->value => 1,
            StructureGroupCode::Department->value => 2,
            StructureGroupCode::UnitSection->value => 3,
        ];

        $query = StructureGrade::query()
            ->with(['level.group', 'level.node.group', 'level.node.parent.group'])
            ->orderBy('sort_order')
            ->orderBy('grade');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()
            ->map(function (StructureGrade $grade) use ($groupSort) {
                $path = $grade->resolvePath();
                $level = $path['level'];
                $levelLabel = null;

                if ($level) {
                    $levelLabel = 'Level '.$level['level_number'];
                    if (! empty($level['reference_title'])) {
                        $levelLabel .= ' · '.$level['reference_title'];
                    }
                }

                $nodePath = null;
                $node = $grade->level?->node;

                if ($node) {
                    $chain = [];
                    $current = $node;
                    while ($current) {
                        $current->loadMissing(['parent.group']);
                        array_unshift($chain, $current->name);
                        $current = $current->parent;
                    }
                    $nodePath = implode(' › ', $chain);
                }

                $gradeLabel = $grade->label();
                $pathLabel = $path['path_label'];
                $suffix = ' › '.$gradeLabel;
                $contextLabel = str_ends_with($pathLabel, $suffix)
                    ? substr($pathLabel, 0, -strlen($suffix))
                    : ($pathLabel === $gradeLabel ? '' : $pathLabel);

                $sectionParts = array_values(array_filter([$nodePath, $levelLabel]));
                $sectionLabel = $sectionParts !== []
                    ? implode(' · ', $sectionParts)
                    : ($path['group']['name'] ?? 'Company structure');

                $groupCode = $path['group']['code'] ?? null;

                return [
                    'id' => $grade->id,
                    'label' => $gradeLabel,
                    'path_label' => $pathLabel,
                    'context_label' => $contextLabel,
                    'group_name' => $path['group']['name'] ?? null,
                    'group_code' => $groupCode,
                    'group_sort' => $groupSort[$groupCode] ?? 99,
                    'node_name' => $path['node']['name'] ?? null,
                    'node_path' => $nodePath,
                    'level_label' => $levelLabel,
                    'section_label' => $sectionLabel,
                    'is_active' => $grade->is_active,
                ];
            })
            ->sortBy([
                ['group_sort', 'asc'],
                ['node_path', 'asc'],
                ['level_label', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createNode(StructureGroup $group, array $attributes): StructureNode
    {
        if (! $group->allowsNodes()) {
            throw ValidationException::withMessages([
                'structure_group_id' => 'This group does not support subgroups.',
            ]);
        }

        $parentId = isset($attributes['parent_id']) ? (int) $attributes['parent_id'] : null;
        $parent = $parentId ? StructureNode::query()->with('group')->findOrFail($parentId) : null;

        $this->assertParentLadder($group->code, $parent);

        $headGradeIds = $this->extractHeadGradeIds($attributes);

        $attributes['structure_group_id'] = $group->id;
        $attributes['parent_id'] = $parentId;
        $attributes['sort_order'] ??= $this->nextNodeSortOrder($parentId);

        $node = StructureNode::query()->create($attributes);

        if ($headGradeIds !== null) {
            $this->syncHeadGrades($node, $headGradeIds, $parentId);
        }

        if ($node->parent_id === null) {
            app(\App\Services\Leave\LeaveApprovalWorkflowService::class)->seedBranchWorkflows($node->fresh() ?? $node);
        }

        return $node->load(['headGrades']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateNode(StructureNode $node, array $attributes): void
    {
        if (array_key_exists('parent_id', $attributes)) {
            $parentId = $attributes['parent_id'] !== null ? (int) $attributes['parent_id'] : null;
            $this->assertValidParent($node, $parentId);
            $attributes['parent_id'] = $parentId;
        }

        $headGradeIds = $this->extractHeadGradeIds($attributes);

        $node->update($attributes);

        if ($headGradeIds !== null) {
            $parentId = array_key_exists('parent_id', $attributes)
                ? ($attributes['parent_id'] !== null ? (int) $attributes['parent_id'] : null)
                : $node->parent_id;

            $this->syncHeadGrades($node, $headGradeIds, $parentId);
        }
    }

    public function deleteNode(StructureNode $node): void
    {
        if ($node->children()->exists()) {
            throw ValidationException::withMessages([
                'node' => 'Remove or move child subgroups before deleting this subgroup.',
            ]);
        }

        if ($this->gradeIdsUnderNode($node)->isNotEmpty()) {
            $assigned = Employee::query()
                ->whereIn('grade_id', $this->gradeIdsUnderNode($node))
                ->exists();

            if ($assigned) {
                throw ValidationException::withMessages([
                    'node' => 'Cannot delete a subgroup that has employees assigned via its grades.',
                ]);
            }
        }

        $node->delete();
    }

    public function moveNode(StructureNode $node, ?int $parentId = null): void
    {
        $this->assertValidParent($node, $parentId);

        $node->update([
            'parent_id' => $parentId,
            'sort_order' => $this->nextNodeSortOrder($parentId),
        ]);
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorderNodes(?int $parentId, array $orderedIds): void
    {
        $nodes = StructureNode::query()
            ->where('parent_id', $parentId)
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');

        if ($nodes->count() !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ordered_ids' => 'Invalid subgroup order payload.',
            ]);
        }

        foreach ($orderedIds as $index => $id) {
            $nodes->get($id)?->update(['sort_order' => $index]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createLevel(array $attributes): StructureLevel
    {
        $this->assertValidLevelOwner($attributes);

        $attributes['sort_order'] ??= $this->nextLevelSortOrder(
            $attributes['structure_group_id'] ?? null,
            $attributes['structure_node_id'] ?? null,
        );

        return StructureLevel::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateLevel(StructureLevel $level, array $attributes): void
    {
        $level->update($attributes);
    }

    public function deleteLevel(StructureLevel $level): void
    {
        $gradeIds = $level->grades()->pluck('id');

        if ($gradeIds->isNotEmpty() && Employee::query()->whereIn('grade_id', $gradeIds)->exists()) {
            throw ValidationException::withMessages([
                'level' => 'Cannot delete a level that has employees assigned via its grades.',
            ]);
        }

        $level->delete();
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorderLevels(?int $groupId, ?int $nodeId, array $orderedIds): void
    {
        $query = StructureLevel::query()->whereIn('id', $orderedIds);

        if ($groupId) {
            $query->where('structure_group_id', $groupId)->whereNull('structure_node_id');
        } else {
            $query->where('structure_node_id', $nodeId);
        }

        $levels = $query->get()->keyBy('id');

        if ($levels->count() !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ordered_ids' => 'Invalid level order payload.',
            ]);
        }

        foreach ($orderedIds as $index => $id) {
            $levels->get($id)?->update(['sort_order' => $index]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createGrade(StructureLevel $level, array $attributes): StructureGrade
    {
        $attributes['structure_level_id'] = $level->id;
        $attributes['sort_order'] ??= $this->nextGradeSortOrder($level->id);

        $grade = StructureGrade::query()->create($attributes);

        app(GradePayrollService::class)->attachMandatoryComponents($grade);

        return $grade;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateGrade(StructureGrade $grade, array $attributes): void
    {
        $grade->update($attributes);
    }

    public function deleteGrade(StructureGrade $grade): void
    {
        if ($grade->employees()->exists()) {
            throw ValidationException::withMessages([
                'grade' => 'Cannot delete a grade that has employees assigned.',
            ]);
        }

        $grade->delete();
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorderGrades(StructureLevel $level, array $orderedIds): void
    {
        $grades = StructureGrade::query()
            ->where('structure_level_id', $level->id)
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');

        if ($grades->count() !== count($orderedIds)) {
            throw ValidationException::withMessages([
                'ordered_ids' => 'Invalid grade order payload.',
            ]);
        }

        foreach ($orderedIds as $index => $id) {
            $grades->get($id)?->update(['sort_order' => $index]);
        }
    }

    /**
     * Grades eligible as a node head: grades on the current node and on its parent
     * (or Strategic Leadership when the node is top-level under the organization root).
     *
     * @return list<array{id: int, label: string, source: string, source_label: string}>
     */
    public function headGradeOptions(?StructureNode $node = null, ?int $parentId = null): array
    {
        $options = [];
        $seen = [];

        $appendGrades = function (iterable $grades, string $source, string $sourceLabel) use (&$options, &$seen): void {
            foreach ($grades as $grade) {
                if (! $grade instanceof StructureGrade || isset($seen[$grade->id])) {
                    continue;
                }

                if (! $grade->is_active) {
                    continue;
                }

                $seen[$grade->id] = true;
                $options[] = [
                    'id' => $grade->id,
                    'label' => $grade->label(),
                    'source' => $source,
                    'source_label' => $sourceLabel,
                ];
            }
        };

        if ($node) {
            $node->loadMissing(['levels.grades']);
            $appendGrades(
                $node->levels->flatMap(fn (StructureLevel $level) => $level->grades),
                'current',
                $node->name,
            );
            $parentId ??= $node->parent_id;
        }

        if ($parentId) {
            $parent = StructureNode::query()->with(['levels.grades'])->find($parentId);
            if ($parent) {
                $appendGrades(
                    $parent->levels->flatMap(fn (StructureLevel $level) => $level->grades),
                    'parent',
                    $parent->name,
                );
            }
        } else {
            $strategic = StructureGroup::query()
                ->where('code', StructureGroupCode::StrategicLeadership)
                ->with(['levels.grades'])
                ->first();

            if ($strategic) {
                $appendGrades(
                    $strategic->levels->flatMap(fn (StructureLevel $level) => $level->grades),
                    'parent',
                    $strategic->name,
                );
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int>|null null when the request did not include head grades
     */
    protected function extractHeadGradeIds(array &$attributes): ?array
    {
        if (! array_key_exists('head_grade_ids', $attributes)) {
            return null;
        }

        $raw = $attributes['head_grade_ids'];
        unset($attributes['head_grade_ids']);

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $raw)));
    }

    /**
     * @param  list<int>  $headGradeIds
     */
    public function syncHeadGrades(StructureNode $node, array $headGradeIds, ?int $parentId = null): void
    {
        $this->assertValidHeadGrades($headGradeIds, $node, $parentId ?? $node->parent_id);
        $node->headGrades()->sync($headGradeIds);
    }

    /**
     * @param  list<int>  $headGradeIds
     */
    public function assertValidHeadGrades(array $headGradeIds, ?StructureNode $node, ?int $parentId): void
    {
        if ($headGradeIds === []) {
            return;
        }

        $allowedIds = collect($this->headGradeOptions($node, $parentId))->pluck('id')->all();

        foreach ($headGradeIds as $headGradeId) {
            if (! in_array($headGradeId, $allowedIds, true)) {
                throw ValidationException::withMessages([
                    'head_grade_ids' => 'Each head must be a grade from this subgroup or its parent.',
                ]);
            }
        }
    }

    public function assertParentLadder(StructureGroupCode $childCode, ?StructureNode $parent): void
    {
        if (! $childCode->allowsNodes()) {
            throw ValidationException::withMessages([
                'structure_group_id' => 'Strategic Leadership cannot contain subgroups.',
            ]);
        }

        $allowed = $childCode->allowedParentCodes();

        if ($parent === null) {
            if (! $childCode->allowsTopLevel()) {
                throw ValidationException::withMessages([
                    'parent_id' => "{$childCode->label()} must belong under a parent in the organization tree.",
                ]);
            }

            return;
        }

        $parent->loadMissing('group');
        $parentCode = $parent->group?->code;

        if (! $parentCode instanceof StructureGroupCode) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent subgroup is missing a group type.',
            ]);
        }

        if ($allowed === []) {
            throw ValidationException::withMessages([
                'parent_id' => "{$childCode->label()} must sit at the top of the organization tree (under Strategic Leadership).",
            ]);
        }

        $allowedValues = array_map(fn (StructureGroupCode $code) => $code->value, $allowed);

        if (! in_array($parentCode->value, $allowedValues, true)) {
            $allowedLabels = implode(' or ', array_map(fn (StructureGroupCode $code) => $code->label(), $allowed));

            throw ValidationException::withMessages([
                'parent_id' => "{$childCode->label()} can only sit under {$allowedLabels}.",
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, StructureNode>>  $nodesByParent
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, Employee>>  $employeesByHeadGrade
     * @return list<array<string, mixed>>
     */
    protected function formatNodeTree($nodesByParent, int|string $parentKey, $employeesByHeadGrade = null): array
    {
        $employeesByHeadGrade ??= collect();

        return ($nodesByParent->get($parentKey) ?? collect())
            ->map(function (StructureNode $node) use ($nodesByParent, $employeesByHeadGrade) {
                $groupCode = $node->group?->code;

                $heads = [];
                $seenEmployeeIds = [];

                foreach ($node->headGrades as $grade) {
                    foreach ($employeesByHeadGrade->get($grade->id, collect()) as $employee) {
                        if (isset($seenEmployeeIds[$employee->id])) {
                            continue;
                        }

                        $seenEmployeeIds[$employee->id] = true;
                        $heads[] = [
                            'id' => $employee->id,
                            'name' => $employee->name,
                            'staff_id' => $employee->staff_id,
                            'grade_id' => $grade->id,
                            'grade_label' => $grade->label(),
                        ];
                    }
                }

                return [
                    'id' => $node->id,
                    'structure_group_id' => $node->structure_group_id,
                    'group_code' => $groupCode?->value,
                    'group_name' => $node->group?->name,
                    'parent_id' => $node->parent_id,
                    'name' => $node->name,
                    'code' => $node->code,
                    'description' => $node->description,
                    'head_grade_ids' => $node->headGrades->pluck('id')->values()->all(),
                    'head_grades' => $node->headGrades->map(fn (StructureGrade $grade) => [
                        'id' => $grade->id,
                        'label' => $grade->label(),
                    ])->values()->all(),
                    'leave_approval_template_id' => $node->leave_approval_template_id
                        ? (int) $node->leave_approval_template_id
                        : null,
                    'overtime_approval_template_id' => $node->overtime_approval_template_id
                        ? (int) $node->overtime_approval_template_id
                        : null,
                    'leave_approval_template_name' => $node->leaveApprovalTemplate?->name,
                    'overtime_approval_template_name' => $node->overtimeApprovalTemplate?->name,
                    'heads' => $heads,
                    'is_active' => $node->is_active,
                    'sort_order' => $node->sort_order,
                    'allowed_child_codes' => $groupCode
                        ? array_map(fn (StructureGroupCode $code) => $code->value, $groupCode->allowedChildCodes())
                        : [],
                    'levels' => $node->levels->map(fn (StructureLevel $level) => $this->formatLevel($level))->all(),
                    'children' => $this->formatNodeTree($nodesByParent, $node->id, $employeesByHeadGrade),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatLevel(StructureLevel $level): array
    {
        return [
            'id' => $level->id,
            'structure_group_id' => $level->structure_group_id,
            'structure_node_id' => $level->structure_node_id,
            'level_number' => $level->level_number,
            'reference_title' => $level->reference_title,
            'sort_order' => $level->sort_order,
            'grades' => $level->grades->map(fn (StructureGrade $grade) => $this->formatGrade($grade))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatGrade(StructureGrade $grade): array
    {
        return [
            'id' => $grade->id,
            'structure_level_id' => $grade->structure_level_id,
            'grade' => $grade->grade,
            'title' => $grade->title,
            'requirements' => $grade->requirements,
            'job_description' => $grade->job_description,
            'label' => $grade->label(),
            'sort_order' => $grade->sort_order,
            'is_active' => $grade->is_active,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function designationsPayload(?string $query = null): array
    {
        $grades = StructureGrade::query()
            ->with([
                'level.group',
                'level.node.group',
                'level.node.parent.group',
            ])
            ->withCount('employees')
            ->orderBy('grade')
            ->orderBy('title')
            ->get();

        $rows = $grades->map(function (StructureGrade $grade) {
            $path = $grade->resolvePath();

            return [
                'id' => $grade->id,
                'grade' => $grade->grade,
                'title' => $grade->title,
                'label' => $grade->label(),
                'requirements' => $grade->requirements,
                'job_description' => $grade->job_description,
                'is_active' => $grade->is_active,
                'path_label' => $path['path_label'],
                'group_name' => $path['group']['name'] ?? null,
                'node_name' => $path['node']['name'] ?? null,
                'level_label' => $path['level']
                    ? 'Level '.$path['level']['level_number'].(
                        $path['level']['reference_title']
                            ? ' ('.$path['level']['reference_title'].')'
                            : ''
                    )
                    : null,
                'employee_count' => (int) $grade->employees_count,
            ];
        });

        if (filled($query)) {
            $needle = mb_strtolower((string) $query);
            $rows = $rows->filter(function (array $row) use ($needle) {
                return str_contains(mb_strtolower((string) $row['label']), $needle)
                    || str_contains(mb_strtolower((string) $row['grade']), $needle)
                    || str_contains(mb_strtolower((string) $row['title']), $needle)
                    || str_contains(mb_strtolower((string) ($row['path_label'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($row['node_name'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($row['group_name'] ?? '')), $needle);
            })->values();
        }

        return $rows->values()->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function assertValidLevelOwner(array $attributes): void
    {
        $groupId = $attributes['structure_group_id'] ?? null;
        $nodeId = $attributes['structure_node_id'] ?? null;

        if (($groupId && $nodeId) || (! $groupId && ! $nodeId)) {
            throw ValidationException::withMessages([
                'structure_group_id' => 'A level must belong to either Strategic Leadership or a subgroup.',
            ]);
        }

        if ($groupId) {
            $group = StructureGroup::query()->findOrFail($groupId);
            if ($group->code !== StructureGroupCode::StrategicLeadership) {
                throw ValidationException::withMessages([
                    'structure_group_id' => 'Only Strategic Leadership can own levels directly.',
                ]);
            }
        }
    }

    protected function assertValidParent(StructureNode $node, ?int $parentId): void
    {
        $node->loadMissing('group');

        if ($parentId === null) {
            $this->assertParentLadder($node->group->code, null);

            return;
        }

        if ($parentId === $node->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A subgroup cannot be its own parent.',
            ]);
        }

        $parent = StructureNode::query()->with('group')->findOrFail($parentId);

        $descendantIds = $this->collectDescendantIds($node);
        if (in_array($parentId, $descendantIds, true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Cannot move a subgroup under one of its descendants.',
            ]);
        }

        $this->assertParentLadder($node->group->code, $parent);
    }

    /**
     * @return list<int>
     */
    protected function collectDescendantIds(StructureNode $node): array
    {
        $ids = [];
        $queue = StructureNode::query()->where('parent_id', $node->id)->pluck('id')->all();

        while ($queue !== []) {
            $id = array_shift($queue);
            $ids[] = $id;
            foreach (StructureNode::query()->where('parent_id', $id)->pluck('id') as $childId) {
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function gradeIdsUnderNode(StructureNode $node)
    {
        $nodeIds = array_merge([$node->id], $this->collectDescendantIds($node));

        return StructureGrade::query()
            ->whereHas('level', fn ($query) => $query->whereIn('structure_node_id', $nodeIds))
            ->pluck('id');
    }

    protected function nextNodeSortOrder(?int $parentId): int
    {
        return (int) StructureNode::query()
            ->where('parent_id', $parentId)
            ->max('sort_order') + 1;
    }

    protected function nextLevelSortOrder(?int $groupId, ?int $nodeId): int
    {
        $query = StructureLevel::query();

        if ($groupId) {
            $query->where('structure_group_id', $groupId)->whereNull('structure_node_id');
        } else {
            $query->where('structure_node_id', $nodeId);
        }

        return (int) $query->max('sort_order') + 1;
    }

    protected function nextGradeSortOrder(int $levelId): int
    {
        return (int) StructureGrade::query()
            ->where('structure_level_id', $levelId)
            ->max('sort_order') + 1;
    }
}
