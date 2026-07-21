<?php

use App\Enums\StructureGroupCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('structure_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('structure_nodes')->nullOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('head_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['structure_group_id', 'code']);
            $table->index(['structure_group_id', 'parent_id', 'sort_order']);
        });

        Schema::create('structure_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('structure_node_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level_number');
            $table->string('reference_title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['structure_group_id', 'sort_order']);
            $table->index(['structure_node_id', 'sort_order']);
        });

        Schema::create('structure_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_level_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 50);
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['structure_level_id', 'sort_order']);
        });

        Schema::create('grade_payroll_component', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_grade_id')->constrained('structure_grades')->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('loan_months')->nullable();
            $table->string('loan_bank', 10)->nullable();
            $table->timestamps();

            $table->unique(['structure_grade_id', 'payroll_component_id'], 'grade_component_unique');
        });

        $now = now();

        foreach ([
            StructureGroupCode::StrategicLeadership,
            StructureGroupCode::Division,
            StructureGroupCode::Department,
            StructureGroupCode::UnitSection,
        ] as $index => $group) {
            DB::table('structure_groups')->insert([
                'code' => $group->value,
                'name' => $group->label(),
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_payroll_component');
        Schema::dropIfExists('structure_grades');
        Schema::dropIfExists('structure_levels');
        Schema::dropIfExists('structure_nodes');
        Schema::dropIfExists('structure_groups');
    }
};
