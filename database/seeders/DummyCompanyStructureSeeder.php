<?php

namespace Database\Seeders;

use App\Enums\StructureGroupCode;
use App\Models\Employee;
use App\Models\StructureGrade;
use App\Models\StructureGroup;
use App\Models\StructureLevel;
use App\Models\StructureNode;
use App\Services\Leave\LeaveApprovalWorkflowService;
use App\Services\Payroll\GradePayrollService;
use Illuminate\Database\Seeder;

/**
 * Seeds a Demo Operations branch with departments, units, levels, and designations,
 * then assigns DEMO### employees onto those designations.
 */
class DummyCompanyStructureSeeder extends Seeder
{
    public function run(): void
    {
        $divisionGroup = StructureGroup::query()->where('code', StructureGroupCode::Division)->firstOrFail();
        $departmentGroup = StructureGroup::query()->where('code', StructureGroupCode::Department)->firstOrFail();
        $unitGroup = StructureGroup::query()->where('code', StructureGroupCode::UnitSection)->firstOrFail();

        $division = $this->upsertNode($divisionGroup, null, [
            'name' => 'Demo Operations Division',
            'code' => 'DEMO_OPS',
            'description' => 'Dummy branch for demo employees, levels, and designations.',
            'sort_order' => 90,
        ]);

        app(LeaveApprovalWorkflowService::class)->seedBranchWorkflows($division);

        $hrDept = $this->upsertNode($departmentGroup, $division->id, [
            'name' => 'Demo Human Resources Department',
            'code' => 'DEMO_HR',
            'description' => 'Dummy HR department.',
            'sort_order' => 1,
        ]);

        $financeDept = $this->upsertNode($departmentGroup, $division->id, [
            'name' => 'Demo Finance Department',
            'code' => 'DEMO_FIN',
            'description' => 'Dummy Finance department.',
            'sort_order' => 2,
        ]);

        $opsDept = $this->upsertNode($departmentGroup, $division->id, [
            'name' => 'Demo Operations Department',
            'code' => 'DEMO_OPD',
            'description' => 'Dummy Operations department.',
            'sort_order' => 3,
        ]);

        $peopleUnit = $this->upsertNode($unitGroup, $hrDept->id, [
            'name' => 'Demo People Operations Unit',
            'code' => 'DEMO_POU',
            'description' => 'Dummy unit under Demo HR.',
            'sort_order' => 1,
        ]);

        $payrollUnit = $this->upsertNode($unitGroup, $financeDept->id, [
            'name' => 'Demo Payroll Unit',
            'code' => 'DEMO_PAY',
            'description' => 'Dummy unit under Demo Finance.',
            'sort_order' => 1,
        ]);

        $fieldUnit = $this->upsertNode($unitGroup, $opsDept->id, [
            'name' => 'Demo Field Services Unit',
            'code' => 'DEMO_FLD',
            'description' => 'Dummy unit under Demo Operations.',
            'sort_order' => 1,
        ]);

        // Division leadership designations
        $divisionGrades = $this->ensureLevelWithGrades($division, 9, 'Demo Division Leadership', [
            ['grade' => 'D2', 'title' => 'Demo Division Director', 'sort_order' => 0],
            ['grade' => 'D1', 'title' => 'Demo Deputy Division Director', 'sort_order' => 1],
        ]);

        // Department designations
        $hrDeptGrades = $this->ensureLevelWithGrades($hrDept, 8, 'Demo HR Leadership', [
            ['grade' => 'H2', 'title' => 'Demo HR Manager', 'sort_order' => 0],
            ['grade' => 'H1', 'title' => 'Demo HR Officer', 'sort_order' => 1],
        ]);

        $finDeptGrades = $this->ensureLevelWithGrades($financeDept, 8, 'Demo Finance Leadership', [
            ['grade' => 'F2', 'title' => 'Demo Finance Manager', 'sort_order' => 0],
            ['grade' => 'F1', 'title' => 'Demo Finance Officer', 'sort_order' => 1],
        ]);

        $opsDeptGrades = $this->ensureLevelWithGrades($opsDept, 8, 'Demo Operations Leadership', [
            ['grade' => 'O2', 'title' => 'Demo Operations Manager', 'sort_order' => 0],
            ['grade' => 'O1', 'title' => 'Demo Operations Supervisor', 'sort_order' => 1],
        ]);

        // Unit / section designations
        $peopleGrades = $this->ensureLevelWithGrades($peopleUnit, 6, 'Demo People Ops Staff', [
            ['grade' => 'P2', 'title' => 'Demo People Ops Lead', 'sort_order' => 0],
            ['grade' => 'P1', 'title' => 'Demo People Ops Assistant', 'sort_order' => 1],
        ]);

        $payrollGrades = $this->ensureLevelWithGrades($payrollUnit, 6, 'Demo Payroll Staff', [
            ['grade' => 'Y2', 'title' => 'Demo Payroll Lead', 'sort_order' => 0],
            ['grade' => 'Y1', 'title' => 'Demo Payroll Clerk', 'sort_order' => 1],
        ]);

        $fieldGrades = $this->ensureLevelWithGrades($fieldUnit, 6, 'Demo Field Staff', [
            ['grade' => 'L2', 'title' => 'Demo Field Lead', 'sort_order' => 0],
            ['grade' => 'L1', 'title' => 'Demo Field Officer', 'sort_order' => 1],
            ['grade' => 'L0', 'title' => 'Demo Field Trainee', 'sort_order' => 2],
        ]);

        // Structure heads for approval path resolution
        $this->syncHeads($division, [$divisionGrades[0]->id]);
        $this->syncHeads($hrDept, [$hrDeptGrades[0]->id]);
        $this->syncHeads($financeDept, [$finDeptGrades[0]->id]);
        $this->syncHeads($opsDept, [$opsDeptGrades[0]->id]);
        $this->syncHeads($peopleUnit, [$peopleGrades[0]->id]);
        $this->syncHeads($payrollUnit, [$payrollGrades[0]->id]);
        $this->syncHeads($fieldUnit, [$fieldGrades[0]->id]);

        $assignmentPool = array_values(array_filter([
            $divisionGrades[0] ?? null,
            $hrDeptGrades[0] ?? null,
            $finDeptGrades[0] ?? null,
            $opsDeptGrades[0] ?? null,
            $peopleGrades[0] ?? null,
            $peopleGrades[1] ?? null,
            $payrollGrades[0] ?? null,
            $payrollGrades[1] ?? null,
            $fieldGrades[0] ?? null,
            $fieldGrades[1] ?? null,
            $fieldGrades[2] ?? null,
            $opsDeptGrades[1] ?? null,
        ]));

        $assigned = $this->assignDemoEmployees($assignmentPool);

        app(GradePayrollService::class)->backfillMissingMandatoryComponents();

        $this->command?->info(sprintf(
            'Demo structure ready: division %s with departments/units, %d designations; assigned %d DEMO employees.',
            $division->name,
            count($assignmentPool) + 2,
            $assigned,
        ));
    }

    /**
     * @param  array{name: string, code: string, description?: string|null, sort_order?: int}  $attributes
     */
    protected function upsertNode(StructureGroup $group, ?int $parentId, array $attributes): StructureNode
    {
        return StructureNode::query()->updateOrCreate(
            [
                'code' => $attributes['code'],
                'structure_group_id' => $group->id,
            ],
            [
                'parent_id' => $parentId,
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'is_active' => true,
                'sort_order' => $attributes['sort_order'] ?? 0,
            ],
        );
    }

    /**
     * @param  list<array{grade: string, title: string, sort_order: int}>  $grades
     * @return list<StructureGrade>
     */
    protected function ensureLevelWithGrades(StructureNode $node, int $levelNumber, string $referenceTitle, array $grades): array
    {
        $level = StructureLevel::query()->updateOrCreate(
            [
                'structure_node_id' => $node->id,
                'level_number' => $levelNumber,
            ],
            [
                'structure_group_id' => null,
                'reference_title' => $referenceTitle,
                'sort_order' => 0,
            ],
        );

        $created = [];

        foreach ($grades as $gradeDef) {
            $grade = StructureGrade::query()->updateOrCreate(
                [
                    'structure_level_id' => $level->id,
                    'grade' => $gradeDef['grade'],
                ],
                [
                    'title' => $gradeDef['title'],
                    'sort_order' => $gradeDef['sort_order'],
                    'is_active' => true,
                    'requirements' => 'Demo designation for testing.',
                    'job_description' => $gradeDef['title'].' — seeded for demo payroll and attendance.',
                ],
            );

            app(GradePayrollService::class)->attachMandatoryComponents($grade);
            $created[] = $grade;
        }

        return $created;
    }

    /**
     * @param  list<int>  $headGradeIds
     */
    protected function syncHeads(StructureNode $node, array $headGradeIds): void
    {
        $node->headGrades()->sync($headGradeIds);
    }

    /**
     * @param  list<StructureGrade>  $grades
     */
    protected function assignDemoEmployees(array $grades): int
    {
        if ($grades === []) {
            return 0;
        }

        $employees = Employee::query()
            ->where('staff_id', 'like', 'DEMO%')
            ->orderBy('staff_id')
            ->get();

        if ($employees->isEmpty()) {
            $this->command?->warn('No DEMO employees found. Run DummyEmployeesAndPunchesSeeder first (or after).');

            return 0;
        }

        $count = 0;

        foreach ($employees as $index => $employee) {
            $grade = $grades[$index % count($grades)];
            $employee->update(['grade_id' => $grade->id]);
            $count++;
        }

        // First demo employee manages the next three when possible.
        $manager = $employees->first();
        if ($manager) {
            foreach ($employees->slice(1, 3) as $report) {
                $report->update(['manager_id' => $manager->id]);
            }
        }

        return $count;
    }
}
