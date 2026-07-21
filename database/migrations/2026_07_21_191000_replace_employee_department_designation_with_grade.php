<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('grade_id')
                ->nullable()
                ->after('designation_id')
                ->constrained('structure_grades')
                ->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('gender')->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
        });
    }
};
