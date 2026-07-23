<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->string('record_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('overtime_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('hours', 8, 2);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('approver_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('manager_reviewed_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('manager_reviewed_at')->nullable();
            $table->text('manager_review_notes')->nullable();
            $table->foreignId('reviewed_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'overtime_date']);
            $table->index(['status', 'approver_employee_id']);
        });

        Schema::create('overtime_request_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_request_id')->constrained('overtime_requests')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('step_key', 40);
            $table->string('label');
            $table->foreignId('approver_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('acted_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['overtime_request_id', 'step_order'], 'ot_req_step_order_unique');
            $table->index(['overtime_request_id', 'status'], 'ot_req_step_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_request_approval_steps');
        Schema::dropIfExists('overtime_requests');
    }
};
