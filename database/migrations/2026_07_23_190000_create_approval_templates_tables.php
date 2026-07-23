<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('steps');
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['kind', 'sort_order']);
        });

        Schema::table('app_settings', function (Blueprint $table): void {
            $table->foreignId('leave_approval_template_id')
                ->nullable()
                ->after('overtime_approval_workflow')
                ->constrained('approval_templates')
                ->nullOnDelete();
            $table->foreignId('overtime_approval_template_id')
                ->nullable()
                ->after('leave_approval_template_id')
                ->constrained('approval_templates')
                ->nullOnDelete();
        });

        Schema::table('structure_nodes', function (Blueprint $table): void {
            $table->foreignId('leave_approval_template_id')
                ->nullable()
                ->after('overtime_approval_workflow')
                ->constrained('approval_templates')
                ->nullOnDelete();
            $table->foreignId('overtime_approval_template_id')
                ->nullable()
                ->after('leave_approval_template_id')
                ->constrained('approval_templates')
                ->nullOnDelete();
        });

        $this->seedAndMigrate();
    }

    public function down(): void
    {
        Schema::table('structure_nodes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_approval_template_id');
            $table->dropConstrainedForeignId('overtime_approval_template_id');
        });

        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_approval_template_id');
            $table->dropConstrainedForeignId('overtime_approval_template_id');
        });

        Schema::dropIfExists('approval_templates');
    }

    protected function seedAndMigrate(): void
    {
        $now = now();

        $presets = [
            'short' => [
                'name' => 'Short',
                'description' => 'Direct manager, then HR.',
                'steps' => [
                    ['key' => 'direct_manager', 'enabled' => true],
                    ['key' => 'unit_section', 'enabled' => false, 'head_grade_ids' => []],
                    ['key' => 'department', 'enabled' => false, 'head_grade_ids' => []],
                    ['key' => 'division', 'enabled' => false, 'head_grade_ids' => []],
                ],
            ],
            'standard' => [
                'name' => 'Standard',
                'description' => 'Direct manager, department head, then HR.',
                'steps' => [
                    ['key' => 'direct_manager', 'enabled' => true],
                    ['key' => 'unit_section', 'enabled' => false, 'head_grade_ids' => []],
                    ['key' => 'department', 'enabled' => true, 'head_grade_ids' => []],
                    ['key' => 'division', 'enabled' => false, 'head_grade_ids' => []],
                ],
            ],
            'full' => [
                'name' => 'Full',
                'description' => 'Direct manager, unit/section, department, division, then HR.',
                'steps' => [
                    ['key' => 'direct_manager', 'enabled' => true],
                    ['key' => 'unit_section', 'enabled' => true, 'head_grade_ids' => []],
                    ['key' => 'department', 'enabled' => true, 'head_grade_ids' => []],
                    ['key' => 'division', 'enabled' => true, 'head_grade_ids' => []],
                ],
            ],
        ];

        $templateIdsByKindAndFingerprint = [];

        foreach (['leave', 'overtime'] as $kind) {
            $sort = 0;
            foreach ($presets as $key => $preset) {
                $id = DB::table('approval_templates')->insertGetId([
                    'kind' => $kind,
                    'name' => $preset['name'],
                    'description' => $preset['description'],
                    'steps' => json_encode($preset['steps']),
                    'is_system' => true,
                    'sort_order' => $sort++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $templateIdsByKindAndFingerprint[$kind][$this->fingerprint($preset['steps'])] = $id;
            }
        }

        $settings = DB::table('app_settings')->orderBy('id')->first();

        if ($settings) {
            $leaveDefaultId = $this->migrateStoredWorkflow(
                $kind = 'leave',
                $settings->leave_approval_workflow ?? null,
                $templateIdsByKindAndFingerprint,
                'Company default (migrated)',
                $now,
            );
            $overtimeDefaultId = $this->migrateStoredWorkflow(
                'overtime',
                $settings->overtime_approval_workflow ?? null,
                $templateIdsByKindAndFingerprint,
                'Company default (migrated)',
                $now,
            );

            // Prefer Full system template when company default was empty / all-on.
            $leaveDefaultId ??= $templateIdsByKindAndFingerprint['leave'][$this->fingerprint($presets['full']['steps'])] ?? null;
            $overtimeDefaultId ??= $templateIdsByKindAndFingerprint['overtime'][$this->fingerprint($presets['full']['steps'])] ?? null;

            DB::table('app_settings')->where('id', $settings->id)->update([
                'leave_approval_template_id' => $leaveDefaultId,
                'overtime_approval_template_id' => $overtimeDefaultId,
                'updated_at' => $now,
            ]);
        }

        $branches = DB::table('structure_nodes')->whereNull('parent_id')->orderBy('id')->get();

        foreach ($branches as $branch) {
            $leaveId = $this->migrateStoredWorkflow(
                'leave',
                $branch->leave_approval_workflow ?? null,
                $templateIdsByKindAndFingerprint,
                "Migrated: {$branch->name} (leave)",
                $now,
            );
            $overtimeId = $this->migrateStoredWorkflow(
                'overtime',
                $branch->overtime_approval_workflow ?? null,
                $templateIdsByKindAndFingerprint,
                "Migrated: {$branch->name} (overtime)",
                $now,
            );

            if ($leaveId === null && $overtimeId === null) {
                continue;
            }

            DB::table('structure_nodes')->where('id', $branch->id)->update([
                'leave_approval_template_id' => $leaveId,
                'overtime_approval_template_id' => $overtimeId,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, array<string, int>>  $cache
     */
    protected function migrateStoredWorkflow(
        string $kind,
        mixed $raw,
        array &$cache,
        string $fallbackName,
        $now,
    ): ?int {
        $steps = $this->normalizeStoredSteps($raw);

        if ($steps === null) {
            return null;
        }

        $fingerprint = $this->fingerprint($steps);

        if (isset($cache[$kind][$fingerprint])) {
            return $cache[$kind][$fingerprint];
        }

        $id = DB::table('approval_templates')->insertGetId([
            'kind' => $kind,
            'name' => $fallbackName,
            'description' => 'Migrated from previous approval workflow settings.',
            'steps' => json_encode($steps),
            'is_system' => false,
            'sort_order' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $cache[$kind][$fingerprint] = $id;

        return $id;
    }

    /**
     * @return list<array{key: string, enabled: bool, head_grade_ids: list<int>}>|null
     */
    protected function normalizeStoredSteps(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw) || ! isset($raw['steps']) || ! is_array($raw['steps']) || $raw['steps'] === []) {
            return null;
        }

        $keys = ['direct_manager', 'unit_section', 'department', 'division'];
        $byKey = [];

        foreach ($raw['steps'] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $key = (string) ($step['key'] ?? '');
            if (! in_array($key, $keys, true)) {
                continue;
            }

            $ids = $step['head_grade_ids'] ?? [];
            if (! is_array($ids)) {
                $ids = [];
            }

            $byKey[$key] = [
                'key' => $key,
                'enabled' => (bool) ($step['enabled'] ?? false),
                'head_grade_ids' => array_values(array_unique(array_filter(
                    array_map('intval', $ids),
                    fn (int $id) => $id > 0,
                ))),
            ];
        }

        if ($byKey === []) {
            return null;
        }

        $normalized = [];
        foreach ($keys as $key) {
            $normalized[] = $byKey[$key] ?? [
                'key' => $key,
                'enabled' => false,
                'head_grade_ids' => [],
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{key: string, enabled: bool, head_grade_ids?: list<int>}>  $steps
     */
    protected function fingerprint(array $steps): string
    {
        $payload = [];
        foreach ($steps as $step) {
            $ids = $step['head_grade_ids'] ?? [];
            sort($ids);
            $payload[] = [
                'key' => $step['key'],
                'enabled' => (bool) ($step['enabled'] ?? false),
                'head_grade_ids' => array_values($ids),
            ];
        }

        return hash('sha256', json_encode($payload));
    }
};
