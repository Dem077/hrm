<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('manager_reviewed_by_employee_id')
                ->nullable()
                ->after('approver_employee_id')
                ->constrained('employees')
                ->nullOnDelete();
            $table->timestamp('manager_reviewed_at')->nullable()->after('manager_reviewed_by_employee_id');
            $table->text('manager_review_notes')->nullable()->after('manager_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_reviewed_by_employee_id');
            $table->dropColumn(['manager_reviewed_at', 'manager_review_notes']);
        });
    }
};
