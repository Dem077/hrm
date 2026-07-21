<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_run_items')) {
            Schema::table('payroll_run_items', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
            });
        }

        Schema::dropIfExists('designation_payroll_component');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable()->unique();
            $table->text('description')->nullable();
            $table->foreignId('head_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable()->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('designation_payroll_component', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('loan_months')->nullable();
            $table->string('loan_bank', 10)->nullable();
            $table->timestamps();

            $table->unique(['designation_id', 'payroll_component_id'], 'designation_component_unique');
        });
    }
};
