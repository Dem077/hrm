<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('structure_nodes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('head_employee_id');
            $table->foreignId('head_grade_id')
                ->nullable()
                ->after('description')
                ->constrained('structure_grades')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('structure_nodes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('head_grade_id');
            $table->foreignId('head_employee_id')
                ->nullable()
                ->after('description')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }
};
