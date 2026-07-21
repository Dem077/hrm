<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_node_head_grade', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('structure_node_id')->constrained('structure_nodes')->cascadeOnDelete();
            $table->foreignId('structure_grade_id')->constrained('structure_grades')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['structure_node_id', 'structure_grade_id'], 'node_head_grade_unique');
        });

        if (Schema::hasColumn('structure_nodes', 'head_grade_id')) {
            $rows = DB::table('structure_nodes')
                ->whereNotNull('head_grade_id')
                ->get(['id', 'head_grade_id']);

            $now = now();

            foreach ($rows as $row) {
                DB::table('structure_node_head_grade')->insert([
                    'structure_node_id' => $row->id,
                    'structure_grade_id' => $row->head_grade_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::table('structure_nodes', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('head_grade_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('structure_nodes', function (Blueprint $table): void {
            $table->foreignId('head_grade_id')
                ->nullable()
                ->after('description')
                ->constrained('structure_grades')
                ->nullOnDelete();
        });

        $firstHeads = DB::table('structure_node_head_grade')
            ->orderBy('id')
            ->get()
            ->groupBy('structure_node_id');

        foreach ($firstHeads as $nodeId => $grades) {
            DB::table('structure_nodes')
                ->where('id', $nodeId)
                ->update(['head_grade_id' => $grades->first()->structure_grade_id]);
        }

        Schema::dropIfExists('structure_node_head_grade');
    }
};
