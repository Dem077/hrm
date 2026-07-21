<?php

namespace Database\Seeders;

use App\Enums\StructureGroupCode;
use App\Models\StructureGrade;
use App\Models\StructureGroup;
use App\Models\StructureLevel;
use App\Models\StructureNode;
use App\Services\Payroll\GradePayrollService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyStructureSeeder extends Seeder
{
    /**
     * Baseline company structure captured from the live database.
     *
     * @return list<array<string, mixed>>
     */
    protected function definition(): array
    {
        return [
            [
                'code' => StructureGroupCode::StrategicLeadership->value,
                'levels' => [
                    [
                        'level_number' => 10,
                        'reference_title' => 'Strategic Leadership',
                        'sort_order' => 0,
                        'grades' => [
                            ['grade' => '4', 'title' => 'Chairperson', 'sort_order' => 0, 'is_active' => true],
                            ['grade' => '3', 'title' => 'Board Members', 'sort_order' => 1, 'is_active' => true],
                            ['grade' => '2', 'title' => 'CEO/MD', 'sort_order' => 2, 'is_active' => true],
                            ['grade' => '1', 'title' => 'DMD', 'sort_order' => 3, 'is_active' => true],
                        ],
                    ],
                ],
                'nodes' => [],
            ],
            [
                'code' => StructureGroupCode::Division->value,
                'levels' => [],
                'nodes' => [
                    [
                        'name' => 'Corporate Affairs Division',
                        'code' => null,
                        'sort_order' => 1,
                        'is_active' => true,
                        'levels' => [
                            [
                                'level_number' => 9,
                                'reference_title' => 'General Manager',
                                'sort_order' => 1,
                                'grades' => [
                                    ['grade' => '3', 'title' => 'Chief Operating Officer (COO)', 'sort_order' => 0, 'is_active' => true],
                                    ['grade' => '2', 'title' => 'Acting Chief Operating Officer (COO)', 'sort_order' => 1, 'is_active' => true],
                                    ['grade' => '1', 'title' => 'Corporate Affairs Director', 'sort_order' => 2, 'is_active' => true],
                                ],
                            ],
                        ],
                        'children' => [],
                    ],
                    [
                        'name' => 'Finance & Accounts Division',
                        'code' => 'FAD',
                        'sort_order' => 1,
                        'is_active' => true,
                        'levels' => [
                            [
                                'level_number' => 9,
                                'reference_title' => 'Manager',
                                'sort_order' => 1,
                                'grades' => [
                                    ['grade' => '3', 'title' => 'Chief Financial Officer (CFO)', 'sort_order' => 0, 'is_active' => true],
                                    ['grade' => '2', 'title' => 'Acting Chief Financial Officer (CFO)', 'sort_order' => 1, 'is_active' => false],
                                    ['grade' => '1', 'title' => 'Finance & Accounts Director', 'sort_order' => 2, 'is_active' => true],
                                ],
                            ],
                        ],
                        'children' => [],
                    ],
                    [
                        'name' => 'HR, Admin, Legal & IT Division',
                        'code' => 'HALIT',
                        'sort_order' => 2,
                        'is_active' => true,
                        'levels' => [
                            [
                                'level_number' => 9,
                                'reference_title' => 'General Manager',
                                'sort_order' => 0,
                                'grades' => [
                                    ['grade' => '2', 'title' => 'Chief Human Resources Officer (CHO)', 'sort_order' => 0, 'is_active' => true],
                                    ['grade' => '1', 'title' => 'HR, Admin, Legal & IT Director', 'sort_order' => 1, 'is_active' => true],
                                ],
                            ],
                        ],
                        'children' => [],
                    ],
                ],
            ],
            [
                'code' => StructureGroupCode::Department->value,
                'levels' => [],
                'nodes' => [
                    [
                        'name' => 'Regional Operations & Logistics Department',
                        'code' => 'ROL',
                        'sort_order' => 1,
                        'is_active' => true,
                        'levels' => [
                            [
                                'level_number' => 8,
                                'reference_title' => 'Asst. General Manager',
                                'sort_order' => 0,
                                'grades' => [
                                    ['grade' => '3', 'title' => 'Head Of Department', 'sort_order' => 0, 'is_active' => true],
                                    ['grade' => '2', 'title' => 'Acting Head Of Department', 'sort_order' => 1, 'is_active' => true],
                                    ['grade' => '1', 'title' => 'Asst Head Of Department', 'sort_order' => 2, 'is_active' => true],
                                ],
                            ],
                        ],
                        'children' => [],
                    ],
                    [
                        'name' => 'HR Department',
                        'code' => 'HR',
                        'sort_order' => 2,
                        'is_active' => true,
                        'levels' => [
                            [
                                'level_number' => 8,
                                'reference_title' => 'Asst. General Manager',
                                'sort_order' => 0,
                                'grades' => [
                                    ['grade' => '3', 'title' => 'Head Of Department', 'sort_order' => 0, 'is_active' => true],
                                    ['grade' => '2', 'title' => 'Acting Head Of Department', 'sort_order' => 1, 'is_active' => true],
                                    ['grade' => '1', 'title' => 'Asst Head Of Department', 'sort_order' => 2, 'is_active' => true],
                                ],
                            ],
                        ],
                        'children' => [],
                    ],
                ],
            ],
            [
                'code' => StructureGroupCode::UnitSection->value,
                'levels' => [],
                'nodes' => [
                    [
                        'name' => 'Production Department',
                        'code' => 'PROD',
                        'sort_order' => 1,
                        'is_active' => true,
                        'levels' => [
                            [
                                'level_number' => 1,
                                'reference_title' => 'Support Staff',
                                'sort_order' => 7,
                                'grades' => [
                                    ['grade' => '2', 'title' => 'Support Executive', 'sort_order' => 3, 'is_active' => true],
                                    ['grade' => '1', 'title' => 'Support Staff', 'sort_order' => 4, 'is_active' => true],
                                ],
                            ],
                        ],
                        'children' => [],
                    ],
                ],
            ],
        ];
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->definition() as $groupDefinition) {
                $group = StructureGroup::query()
                    ->where('code', $groupDefinition['code'])
                    ->firstOrFail();

                foreach ($groupDefinition['levels'] as $levelDefinition) {
                    $this->seedLevel($group->id, null, $levelDefinition);
                }

                foreach ($groupDefinition['nodes'] as $nodeDefinition) {
                    $this->seedNode($group->id, null, $nodeDefinition);
                }
            }
        });

        app(GradePayrollService::class)->backfillMissingMandatoryComponents();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function seedNode(int $groupId, ?int $parentId, array $definition): StructureNode
    {
        $lookup = [
            'structure_group_id' => $groupId,
            'parent_id' => $parentId,
        ];

        if (filled($definition['code'] ?? null)) {
            $lookup['code'] = $definition['code'];
            $node = StructureNode::query()->updateOrCreate($lookup, [
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'is_active' => $definition['is_active'] ?? true,
                'sort_order' => $definition['sort_order'] ?? 0,
            ]);
        } else {
            $node = StructureNode::query()->updateOrCreate(
                [
                    ...$lookup,
                    'name' => $definition['name'],
                ],
                [
                    'code' => null,
                    'description' => $definition['description'] ?? null,
                    'is_active' => $definition['is_active'] ?? true,
                    'sort_order' => $definition['sort_order'] ?? 0,
                ],
            );
        }

        foreach ($definition['levels'] ?? [] as $levelDefinition) {
            $this->seedLevel(null, $node->id, $levelDefinition);
        }

        foreach ($definition['children'] ?? [] as $childDefinition) {
            $this->seedNode($groupId, $node->id, $childDefinition);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function seedLevel(?int $groupId, ?int $nodeId, array $definition): StructureLevel
    {
        $level = StructureLevel::query()->updateOrCreate(
            [
                'structure_group_id' => $groupId,
                'structure_node_id' => $nodeId,
                'level_number' => $definition['level_number'],
            ],
            [
                'reference_title' => $definition['reference_title'],
                'sort_order' => $definition['sort_order'] ?? 0,
            ],
        );

        foreach ($definition['grades'] ?? [] as $gradeDefinition) {
            StructureGrade::query()->updateOrCreate(
                [
                    'structure_level_id' => $level->id,
                    'grade' => $gradeDefinition['grade'],
                ],
                [
                    'title' => $gradeDefinition['title'],
                    'sort_order' => $gradeDefinition['sort_order'] ?? 0,
                    'is_active' => $gradeDefinition['is_active'] ?? true,
                ],
            );
        }

        return $level;
    }
}
