<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('period_label');
            $table->string('period_source', 20)->default('global');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('finalised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalised_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedInteger('finalised_version')->default(0);
            $table->timestamps();

            $table->index(['period_from', 'period_to']);
            $table->index('status');
        });

        Schema::create('payroll_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('department_name')->nullable();
            $table->string('designation_name')->nullable();
            $table->string('staff_id')->nullable();
            $table->string('employee_name');
            $table->string('national_id')->nullable();
            $table->unsignedInteger('days_attended')->default(0);
            $table->decimal('hours_worked', 10, 2)->default(0);
            $table->decimal('base_gross', 12, 2)->default(0);
            $table->decimal('base_deductions', 12, 2)->default(0);
            $table->decimal('base_net', 12, 2)->default(0);
            $table->decimal('manual_additions', 12, 2)->default(0);
            $table->decimal('manual_deductions', 12, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->json('details')->nullable();
            $table->json('attendance_summary')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['payroll_run_id', 'department_name']);
        });

        Schema::create('payroll_run_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payroll_run_id', 'employee_id']);
            $table->index('type');
        });

        Schema::create('payroll_run_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('password_confirmed')->default(false);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payroll_run_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_audit_logs');
        Schema::dropIfExists('payroll_run_adjustments');
        Schema::dropIfExists('payroll_run_items');
        Schema::dropIfExists('payroll_runs');
    }
};
