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
            ->with([
                'levels.grades',
                'allNodes' => fn ($query) => $query
                    ->with(['headEmployee:id,name,staff_id', 'levels.grades', 'children'])
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->get();

        return $groups->map(fn (StructureGroup $group) => $this->formatGroup($group))->all();
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
            ->with(['level.group', 'level.node.group', 'level.node.parent'])
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

        $parentId = $attributes['parent_id'] ?? null;

        if ($parentId) {
            $parent = StructureNode::query()->findOrFail($parentId);
            if ($parent->structure_group_id !== $group->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent must belong to the same group.',
                ]);
            }
        }

        $attributes['structure_group_id'] = $group->id;
        $attributes['sort_order'] ??= $this->nextNodeSortOrder($group->id, $parentId);

        return StructureNode::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateNode(StructureNode $node, array $attributes): void
    {
        if (array_key_exists('parent_id', $attributes)) {
            $this->assertValidParent($node, $attributes['parent_id']);
        }

        $node->update($attributes);
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

    public function moveNode(StructureNode $node, int $targetGroupId, ?int $parentId = null): void
    {
        $targetGroup = StructureGroup::query()->findOrFail($targetGroupId);

        if (! $targetGroup->allowsNodes()) {
            throw ValidationException::withMessages([
                'structure_group_id' => 'Subgroups cannot be moved into Strategic Leadership.',
            ]);
        }

        $sourceGroup = $node->group()->first();
        if ($sourceGroup && ! $sourceGroup->allowsNodes()) {
            throw ValidationException::withMessages([
                'structure_group_id' => 'Strategic Leadership does not contain movable subgroups.',
            ]);
        }

        $this->assertValidParentForMove($node, $targetGroupId, $parentId);

        if (
            $node->code
            && StructureNode::query()
                ->where('structure_group_id', $targetGroupId)
                ->where('code', $node->code)
                ->whereKeyNot($node->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'structure_group_id' => "A subgroup with code \"{$node->code}\" already exists in {$targetGroup->name}.",
            ]);
        }

        DB::transaction(function () use ($node, $targetGroupId, $parentId): void {
            $descendantIds = $this->collectDescendantIds($node);
            $allIds = array_merge([$node->id], $descendantIds);

            StructureNode::query()
                ->whereIn('id', $allIds)
                ->update(['structure_group_id' => $targetGroupId]);

            $node->refresh();
            $node->update([
                'parent_id' => $parentId,
                'sort_order' => $this->nextNodeSortOrder($targetGroupId, $parentId),
            ]);
        });
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorderNodes(?int $parentId, int $groupId, array $orderedIds): void
    {
        $nodes = StructureNode::query()
            ->where('structure_group_id', $groupId)
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
     * @return list<array{id: int, name: string, staff_id: string}>
     */
    public function headOptions(): array
    {
        return Employee::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'staff_id'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'staff_id' => $employee->staff_id,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatGroup(StructureGroup $group): array
    {
        $nodesByParent = $group->allNodes->groupBy(fn (StructureNode $node) => $node->parent_id ?? 0);

        return [
            'id' => $group->id,
            'code' => $group->code->value,
            'name' => $group->name,
            'sort_order' => $group->sort_order,
            'allows_nodes' => $group->allowsNodes(),
            'levels' => $group->allowsNodes()
                ? []
                : $group->levels->map(fn (StructureLevel $level) => $this->formatLevel($level))->all(),
            'nodes' => $group->allowsNodes()
                ? $this->formatNodeTree($nodesByParent, 0)
                : [],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, StructureNode>>  $nodesByParent
     * @return list<array<string, mixed>>
     */
    protected function formatNodeTree($nodesByParent, int|string $parentKey): array
    {
        return ($nodesByParent->get($parentKey) ?? collect())
            ->map(function (StructureNode $node) use ($nodesByParent) {
                return [
                    'id' => $node->id,
                    'structure_group_id' => $node->structure_group_id,
                    'parent_id' => $node->parent_id,
                    'name' => $node->name,
                    'code' => $node->code,
                    'description' => $node->description,
                    'head_employee_id' => $node->head_employee_id,
                    'head_employee' => $node->headEmployee ? [
                        'id' => $node->headEmployee->id,
                        'name' => $node->headEmployee->name,
                        'staff_id' => $node->headEmployee->staff_id,
                    ] : null,
                    'is_active' => $node->is_active,
                    'sort_order' => $node->sort_order,
                    'levels' => $node->levels->map(fn (StructureLevel $level) => $this->formatLevel($level))->all(),
                    'children' => $this->formatNodeTree($nodesByParent, $node->id),
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
            'label' => $grade->label(),
            'sort_order' => $grade->sort_order,
            'is_active' => $grade->is_active,
        ];
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
        $this->assertValidParentForMove($node, $node->structure_group_id, $parentId);
    }

    protected function assertValidParentForMove(StructureNode $node, int $targetGroupId, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $node->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A subgroup cannot be its own parent.',
            ]);
        }

        $parent = StructureNode::query()->findOrFail($parentId);

        if ($parent->structure_group_id !== $targetGroupId) {
            throw ValidationException::withMessages([
                'parent_id' => 'Parent must belong to the selected destination group.',
            ]);
        }

        $descendantIds = $this->collectDescendantIds($node);
        if (in_array($parentId, $descendantIds, true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Cannot move a subgroup under one of its descendants.',
            ]);
        }
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

    protected function nextNodeSortOrder(int $groupId, ?int $parentId): int
    {
        return (int) StructureNode::query()
            ->where('structure_group_id', $groupId)
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
