<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->json('leave_approval_workflow')->nullable()->after('leave_carry_forward_enabled');
        });

        Schema::create('leave_request_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('step_key', 40);
            $table->string('label');
            $table->foreignId('approver_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('acted_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['leave_request_id', 'step_order']);
            $table->index(['leave_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_approval_steps');

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('leave_approval_workflow');
        });
    }
};
