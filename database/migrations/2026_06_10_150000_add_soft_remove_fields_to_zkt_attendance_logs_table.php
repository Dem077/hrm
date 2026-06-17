<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkt_attendance_logs', function (Blueprint $table) {
            $table->text('removal_reason')->nullable()->after('added_by_user_id');
            $table->foreignId('removed_by_user_id')->nullable()->after('removal_reason')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('zkt_attendance_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('removed_by_user_id');
            $table->dropColumn('removal_reason');
        });
    }
};
