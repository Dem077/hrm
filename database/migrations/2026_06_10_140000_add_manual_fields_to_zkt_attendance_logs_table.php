<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkt_attendance_logs', function (Blueprint $table) {
            $table->string('source')->default('device')->after('punched_at');
            $table->text('manual_reason')->nullable()->after('source');
            $table->foreignId('added_by_user_id')->nullable()->after('manual_reason')->constrained('users')->nullOnDelete();

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('zkt_attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('added_by_user_id');
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'manual_reason']);
        });
    }
};
