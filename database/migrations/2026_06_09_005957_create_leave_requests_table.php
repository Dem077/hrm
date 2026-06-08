<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days_count');
            $table->text('reason');
            $table->string('document_path')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approver_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('reviewed_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'approver_employee_id']);
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
