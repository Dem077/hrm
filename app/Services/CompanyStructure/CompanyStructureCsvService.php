<?php

namespace App\Services\CompanyStructure;

use App\Enums\StructureGroupCode;
use App\Models\StructureGrade;
use App\Models\StructureGroup;
use App\Models\StructureLevel;
use App\Models\StructureNode;
use App\Services\Payroll\GradePayrollService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyStructureCsvService
{
    public const HEADERS = [
        'group_code',
        'node_name',
        'node_code',
        'parent_node_name',
        'level_number',
        'reference_title',
        'grade',
        'grade_title',
    ];

    public function __construct(
        protected GradePayrollService $gradePayrollService,
    ) {}

    public function downloadSample(): StreamedResponse
    {
        $filename = 'company-structure-sample.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, self::HEADERS);

            foreach ($this->sampleRows() as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, summary: array{rows: int, create_nodes: int, update_nodes: int, create_levels: int, update_levels: int, create_grades: int, update_grades: int}}
     */
    public function preview(UploadedFile $file): array
    {
        $rows = $this->requireDataRows($file);
        $groups = StructureGroup::query()->get()->keyBy(fn (StructureGroup $group) => $group->code->value);

        $previewRows = [];
        $pendingNodeNames = [];
        $pendingLevelKeys = [];
        $pendingGradeKeys = [];
        $seenNodeActions = [];
        $seenLevelActions = [];
        $sortState = $this->emptySortState();

        $summary = [
            'rows' => count($rows),
            'create_nodes' => 0,
            'update_nodes' => 0,
            'create_levels' => 0,
            'update_levels' => 0,
            'create_grades' => 0,
            'update_grades' => 0,
        ];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $validated = $this->validateRow($row, $line, $groups, $pendingNodeNames, $sortState);

            $group = $validated['group'];
            $nodeName = $validated['node_name'];
            $nodeCode = $validated['node_code'];
            $parentName = $validated['parent_name'];
            $parentId = $validated['parent_id'];
            $levelNumber = $validated['level_number'];
            $gradeCode = $validated['grade'];

            $nodeAction = 'none';
            $existingNode = null;

            if ($group->code !== StructureGroupCode::StrategicLeadership) {
                $existingNode = $this->findExistingNode($group->id, $parentId, $nodeName, $nodeCode);
                $nodeKey = $group->id.'|'.mb_strtolower($nodeName);

                if ($existingNode) {
                    $nodeAction = 'update';
                } else {
                    $nodeAction = 'create';
                    $pendingNodeNames[mb_strtolower($nodeName)] = $group->code;
                }

                if (! isset($seenNodeActions[$nodeKey])) {
                    $seenNodeActions[$nodeKey] = $nodeAction;
                    $summary[$nodeAction === 'create' ? 'create_nodes' : 'update_nodes']++;
                }
            }

            $levelKey = $group->id.'|'.mb_strtolower($nodeName).'|'.$levelNumber;
            $existingLevel = null;

            if ($existingNode) {
                $existingLevel = StructureLevel::query()
                    ->where('structure_node_id', $existingNode->id)
                    ->whereNull('structure_group_id')
                    ->where('level_number', $levelNumber)
                    ->first();
            } elseif ($group->code === StructureGroupCode::StrategicLeadership) {
                $existingLevel = StructureLevel::query()
                    ->where('structure_group_id', $group->id)
                    ->whereNull('structure_node_id')
                    ->where('level_number', $levelNumber)
                    ->first();
            }

            if ($existingLevel || isset($pendingLevelKeys[$levelKey])) {
                $levelAction = $existingLevel ? 'update' : 'create';
            } else {
                $levelAction = 'create';
                $pendingLevelKeys[$levelKey] = true;
            }

            if (! isset($seenLevelActions[$levelKey])) {
                $seenLevelActions[$levelKey] = $levelAction;
                $summary[$levelAction === 'create' ? 'create_levels' : 'update_levels']++;
            }

            $gradeKey = $levelKey.'|'.mb_strtolower($gradeCode);
            $existingGrade = null;

            if ($existingLevel) {
                $existingGrade = StructureGrade::query()
                    ->where('structure_level_id', $existingLevel->id)
                    ->where('grade', $gradeCode)
                    ->first();
            }

            if ($existingGrade) {
                $gradeAction = 'update';
            } elseif (isset($pendingGradeKeys[$gradeKey])) {
                $gradeAction = 'create';
            } else {
                $gradeAction = 'create';
                $pendingGradeKeys[$gradeKey] = true;
            }

            $summary[$gradeAction === 'create' ? 'create_grades' : 'update_grades']++;

            $previewRows[] = [
                'line' => $line,
                'group_code' => $group->code->value,
                'group_name' => $group->name,
                'node_name' => $nodeName,
                'node_code' => $nodeCode ?? '',
                'parent_node_name' => $parentName,
                'level_number' => $levelNumber,
                'reference_title' => $validated['reference_title'],
                'grade' => $gradeCode,
                'grade_title' => $validated['grade_title'],
                'node_action' => $nodeAction,
                'level_action' => $levelAction,
                'grade_action' => $gradeAction,
            ];
        }

        return [
            'rows' => $previewRows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array{created_nodes: int, updated_nodes: int, created_levels: int, updated_levels: int, created_grades: int, updated_grades: int, rows: int}
     */
    public function import(UploadedFile $file): array
    {
        $rows = $this->requireDataRows($file);

        $stats = [
            'created_nodes' => 0,
            'updated_nodes' => 0,
            'created_levels' => 0,
            'updated_levels' => 0,
            'created_grades' => 0,
            'updated_grades' => 0,
            'rows' => count($rows),
        ];

        $seenNodes = [];
        $seenLevels = [];

        DB::transaction(function () use ($rows, &$stats, &$seenNodes, &$seenLevels): void {
            $groups = StructureGroup::query()->get()->keyBy(fn (StructureGroup $group) => $group->code->value);
            $pendingNodeNames = [];
            $sortState = $this->emptySortState();

            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $validated = $this->validateRow($row, $line, $groups, $pendingNodeNames, $sortState);

                $group = $validated['group'];
                $nodeName = $validated['node_name'];
                $nodeCode = $validated['node_code'];
                $parentId = $validated['parent_id'];
                $levelNumber = $validated['level_number'];
                $referenceTitle = $validated['reference_title'];
                $gradeCode = $validated['grade'];
                $gradeTitle = $validated['grade_title'];
                $gradeActive = $validated['grade_active'];
                $nodeSort = $validated['node_sort_order'];
                $levelSort = $validated['level_sort_order'];
                $gradeSort = $validated['grade_sort_order'];

                $node = null;

                if ($group->code !== StructureGroupCode::StrategicLeadership) {
                    $existing = $this->findExistingNode($group->id, $parentId, $nodeName, $nodeCode);

                    if ($existing) {
                        $parent = $parentId
                            ? StructureNode::query()->with('group')->find($parentId)
                            : null;

                        app(CompanyStructureService::class)->assertParentLadder($group->code, $parent);

                        $existing->update([
                            'name' => $nodeName,
                            'code' => $nodeCode,
                            'parent_id' => $parentId,
                            'sort_order' => $nodeSort,
                            'is_active' => true,
                        ]);
                        $node = $existing->fresh();
                        if (! isset($seenNodes[$node->id])) {
                            $seenNodes[$node->id] = true;
                            $stats['updated_nodes']++;
                        }
                    } else {
                        $parent = $parentId
                            ? StructureNode::query()->with('group')->find($parentId)
                            : null;

                        app(CompanyStructureService::class)->assertParentLadder($group->code, $parent);

                        $node = StructureNode::query()->create([
                            'structure_group_id' => $group->id,
                            'parent_id' => $parentId,
                            'name' => $nodeName,
                            'code' => $nodeCode,
                            'sort_order' => $nodeSort,
                            'is_active' => true,
                        ]);
                        $seenNodes[$node->id] = true;
                        $stats['created_nodes']++;
                    }

                    $pendingNodeNames[mb_strtolower($nodeName)] = $group->code;
                }

                $levelQuery = StructureLevel::query()->where('level_number', $levelNumber);

                if ($node) {
                    $levelQuery->where('structure_node_id', $node->id)->whereNull('structure_group_id');
                } else {
                    $levelQuery->where('structure_group_id', $group->id)->whereNull('structure_node_id');
                }

                $level = $levelQuery->first();

                if ($level) {
                    $level->update([
                        'reference_title' => $referenceTitle,
                        'sort_order' => $levelSort,
                    ]);
                    if (! isset($seenLevels[$level->id])) {
                        $seenLevels[$level->id] = true;
                        $stats['updated_levels']++;
                    }
                } else {
                    $level = StructureLevel::query()->create([
                        'structure_group_id' => $node ? null : $group->id,
                        'structure_node_id' => $node?->id,
                        'level_number' => $levelNumber,
                        'reference_title' => $referenceTitle,
                        'sort_order' => $levelSort,
                    ]);
                    $seenLevels[$level->id] = true;
                    $stats['created_levels']++;
                }

                $grade = StructureGrade::query()
                    ->where('structure_level_id', $level->id)
                    ->where('grade', $gradeCode)
                    ->first();

                if ($grade) {
                    $grade->update([
                        'title' => $gradeTitle,
                        'sort_order' => $gradeSort,
                        'is_active' => $gradeActive,
                    ]);
                    $stats['updated_grades']++;
                } else {
                    $grade = StructureGrade::query()->create([
                        'structure_level_id' => $level->id,
                        'grade' => $gradeCode,
                        'title' => $gradeTitle,
                        'sort_order' => $gradeSort,
                        'is_active' => $gradeActive,
                    ]);
                    $this->gradePayrollService->attachMandatoryComponents($grade);
                    $stats['created_grades']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @return list<array<string, string>>
     */
    protected function requireDataRows(UploadedFile $file): array
    {
        $rows = $this->parseCsv($file);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'The CSV file has no data rows.',
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @param  \Illuminate\Support\Collection<string, StructureGroup>  $groups
     * @param  array<string, StructureGroupCode>  $pendingNodeNames lowercase name => group code
     * @param  array{
     *     nodes: array<string, int>,
     *     node_next: array<string, int>,
     *     levels: array<string, int>,
     *     level_next: array<string, int>,
     *     grades: array<string, int>,
     *     grade_next: array<string, int>
     * }  $sortState
     * @return array{
     *     group: StructureGroup,
     *     node_name: string,
     *     node_code: ?string,
     *     parent_name: string,
     *     parent_id: ?int,
     *     level_number: int,
     *     reference_title: string,
     *     grade: string,
     *     grade_title: string,
     *     grade_active: bool,
     *     node_sort_order: int,
     *     level_sort_order: int,
     *     grade_sort_order: int
     * }
     */
    protected function validateRow(array $row, int $line, $groups, array $pendingNodeNames, array &$sortState): array
    {
        $groupCode = strtolower(trim((string) ($row['group_code'] ?? '')));

        if ($groupCode === '') {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: group_code is required.",
            ]);
        }

        /** @var StructureGroup|null $group */
        $group = $groups->get($groupCode);

        if (! $group) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: unknown group_code \"{$groupCode}\". Use strategic_leadership, division, department, or unit_section.",
            ]);
        }

        $levelNumber = (int) ($row['level_number'] ?? 0);
        $referenceTitle = trim((string) ($row['reference_title'] ?? ''));
        $gradeCode = trim((string) ($row['grade'] ?? ''));
        $gradeTitle = trim((string) ($row['grade_title'] ?? ''));

        if ($levelNumber < 1) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: level_number must be a positive integer.",
            ]);
        }

        if ($referenceTitle === '') {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: reference_title is required.",
            ]);
        }

        if ($gradeCode === '' || $gradeTitle === '') {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: grade and grade_title are required.",
            ]);
        }

        $nodeName = trim((string) ($row['node_name'] ?? ''));
        $nodeCode = trim((string) ($row['node_code'] ?? '')) ?: null;
        $parentName = trim((string) ($row['parent_node_name'] ?? ''));
        $parentGroupCodeHint = strtolower(trim((string) ($row['parent_group_code'] ?? ''))) ?: null;
        $parentId = null;

        if ($group->code === StructureGroupCode::StrategicLeadership) {
            if ($nodeName !== '') {
                throw ValidationException::withMessages([
                    'file' => "Row {$line}: Strategic Leadership cannot have subgroups. Leave node_name empty.",
                ]);
            }
        } else {
            if ($nodeName === '') {
                throw ValidationException::withMessages([
                    'file' => "Row {$line}: node_name is required for {$group->name}.",
                ]);
            }

            $allowedParents = $group->code->allowedParentCodes();

            if ($group->code->requiresParent() && $parentName === '') {
                $labels = implode(' or ', array_map(fn (StructureGroupCode $code) => $code->label(), $allowedParents));

                throw ValidationException::withMessages([
                    'file' => "Row {$line}: parent_node_name is required for {$group->name} (must be a {$labels}).",
                ]);
            }

            if (! $group->code->requiresParent() && $parentName !== '') {
                throw ValidationException::withMessages([
                    'file' => "Row {$line}: Divisions must leave parent_node_name empty (they sit under Strategic Leadership).",
                ]);
            }

            if ($parentName !== '') {
                $resolved = $this->resolveParentNode(
                    $line,
                    $parentName,
                    $allowedParents,
                    $pendingNodeNames,
                    $parentGroupCodeHint,
                );
                $parentId = $resolved;
            }
        }

        $siblingScope = $group->id.'|'.mb_strtolower($parentName);
        $nodeKey = $siblingScope.'|'.mb_strtolower($nodeName);
        $nodeSort = 0;

        if ($group->code !== StructureGroupCode::StrategicLeadership) {
            if (! isset($sortState['nodes'][$nodeKey])) {
                $sortState['nodes'][$nodeKey] = $sortState['node_next'][$siblingScope] ?? 0;
                $sortState['node_next'][$siblingScope] = $sortState['nodes'][$nodeKey] + 1;
            }

            $nodeSort = $sortState['nodes'][$nodeKey];
        }

        $levelScope = $group->id.'|'.mb_strtolower($nodeName);
        $levelKey = $levelScope.'|'.$levelNumber;

        if (! isset($sortState['levels'][$levelKey])) {
            $sortState['levels'][$levelKey] = $sortState['level_next'][$levelScope] ?? 0;
            $sortState['level_next'][$levelScope] = $sortState['levels'][$levelKey] + 1;
        }

        $gradeKey = $levelKey.'|'.mb_strtolower($gradeCode);

        if (! isset($sortState['grades'][$gradeKey])) {
            $sortState['grades'][$gradeKey] = $sortState['grade_next'][$levelKey] ?? 0;
            $sortState['grade_next'][$levelKey] = $sortState['grades'][$gradeKey] + 1;
        }

        return [
            'group' => $group,
            'node_name' => $nodeName,
            'node_code' => $nodeCode,
            'parent_name' => $parentName,
            'parent_id' => $parentId,
            'level_number' => $levelNumber,
            'reference_title' => $referenceTitle,
            'grade' => $gradeCode,
            'grade_title' => $gradeTitle,
            'grade_active' => true,
            'node_sort_order' => $nodeSort,
            'level_sort_order' => $sortState['levels'][$levelKey],
            'grade_sort_order' => $sortState['grades'][$gradeKey],
        ];
    }

    /**
     * @param  list<StructureGroupCode>  $allowedParents
     * @param  array<string, StructureGroupCode>  $pendingNodeNames
     */
    protected function resolveParentNode(
        int $line,
        string $parentName,
        array $allowedParents,
        array $pendingNodeNames,
        ?string $parentGroupCodeHint,
    ): ?int {
        $allowedValues = array_map(fn (StructureGroupCode $code) => $code->value, $allowedParents);
        $allowedLabels = implode(' or ', array_map(fn (StructureGroupCode $code) => $code->label(), $allowedParents));

        if ($parentGroupCodeHint !== null && ! in_array($parentGroupCodeHint, $allowedValues, true)) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: parent_group_code \"{$parentGroupCodeHint}\" is not valid for this row (expected {$allowedLabels}).",
            ]);
        }

        $query = StructureNode::query()
            ->with('group')
            ->where('name', $parentName)
            ->whereHas('group', function ($groupQuery) use ($allowedValues, $parentGroupCodeHint): void {
                $groupQuery->whereIn('code', $allowedValues);

                if ($parentGroupCodeHint !== null) {
                    $groupQuery->where('code', $parentGroupCodeHint);
                }
            });

        $matches = $query->get();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: parent_node_name \"{$parentName}\" matches multiple subgroups. Add an optional parent_group_code column (division, department, or unit_section) to disambiguate.",
            ]);
        }

        if ($matches->count() === 1) {
            return $matches->first()?->id;
        }

        $pendingKey = mb_strtolower($parentName);
        $pendingCode = $pendingNodeNames[$pendingKey] ?? null;

        if ($pendingCode instanceof StructureGroupCode && in_array($pendingCode->value, $allowedValues, true)) {
            if ($parentGroupCodeHint !== null && $pendingCode->value !== $parentGroupCodeHint) {
                throw ValidationException::withMessages([
                    'file' => "Row {$line}: pending parent \"{$parentName}\" is a {$pendingCode->label()}, not {$parentGroupCodeHint}.",
                ]);
            }

            return null;
        }

        throw ValidationException::withMessages([
            'file' => "Row {$line}: parent_node_name \"{$parentName}\" was not found among {$allowedLabels}. Import parent rows first.",
        ]);
    }

    /**
     * @return array{
     *     nodes: array<string, int>,
     *     node_next: array<string, int>,
     *     levels: array<string, int>,
     *     level_next: array<string, int>,
     *     grades: array<string, int>,
     *     grade_next: array<string, int>
     * }
     */
    protected function emptySortState(): array
    {
        return [
            'nodes' => [],
            'node_next' => [],
            'levels' => [],
            'level_next' => [],
            'grades' => [],
            'grade_next' => [],
        ];
    }

    protected function findExistingNode(int $groupId, ?int $parentId, string $nodeName, ?string $nodeCode): ?StructureNode
    {
        return StructureNode::query()
            ->where('structure_group_id', $groupId)
            ->where('parent_id', $parentId)
            ->where(function ($query) use ($nodeCode, $nodeName): void {
                if ($nodeCode) {
                    $query->where('code', $nodeCode)
                        ->orWhere('name', $nodeName);
                } else {
                    $query->where('name', $nodeName);
                }
            })
            ->first();
    }

    /**
     * @return list<list<string|int>>
     */
    protected function sampleRows(): array
    {
        return [
            ['strategic_leadership', '', '', '', 10, 'Strategic Leadership', '4', 'Chairperson'],
            ['strategic_leadership', '', '', '', 10, 'Strategic Leadership', '3', 'Board Members'],
            ['strategic_leadership', '', '', '', 10, 'Strategic Leadership', '2', 'CEO/MD'],
            ['strategic_leadership', '', '', '', 10, 'Strategic Leadership', '1', 'DMD'],
            ['division', 'Corporate Affairs Division', 'CAD', '', 9, 'General Manager', '3', 'Chief Operating Officer (COO)'],
            ['division', 'Corporate Affairs Division', 'CAD', '', 9, 'General Manager', '2', 'Acting Chief Operating Officer (COO)'],
            ['division', 'Corporate Affairs Division', 'CAD', '', 9, 'General Manager', '1', 'Corporate Affairs Director'],
            ['division', 'Finance & Accounts Division', 'FAD', '', 9, 'Manager', '3', 'Chief Financial Officer (CFO)'],
            ['department', 'HR Department', 'HR', 'Corporate Affairs Division', 8, 'Asst. General Manager', '3', 'Head Of Department'],
            ['department', 'HR Department', 'HR', 'Corporate Affairs Division', 8, 'Asst. General Manager', '2', 'Acting Head Of Department'],
            ['department', 'HR Department', 'HR', 'Corporate Affairs Division', 8, 'Asst. General Manager', '1', 'Asst Head Of Department'],
            ['unit_section', 'Production Section', 'PROD', 'HR Department', 1, 'Support Staff', '2', 'Support Executive'],
            ['unit_section', 'Production Section', 'PROD', 'HR Department', 1, 'Support Staff', '1', 'Support Staff'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    protected function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'Unable to read the uploaded CSV file.',
            ]);
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => 'The CSV file is empty.',
            ]);
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);

        // Strip UTF-8 BOM from first header if present.
        if ($header !== [] && str_starts_with($header[0], "\xEF\xBB\xBF")) {
            $header[0] = substr($header[0], 3);
        }

        foreach (self::HEADERS as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => "Missing required CSV column: {$required}. Download the sample CSV for the correct format.",
                ]);
            }
        }

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $row = [];

            foreach ($header as $index => $column) {
                $row[$column] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>|false  $data
     */
    protected function rowIsEmpty(array|false $data): bool
    {
        if ($data === false) {
            return true;
        }

        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
