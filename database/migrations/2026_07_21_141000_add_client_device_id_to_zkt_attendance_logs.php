<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkt_attendance_logs', function (Blueprint $table) {
            $table->uuid('client_device_id')->nullable()->after('request_ip');
            $table->index(['source', 'client_device_id', 'punched_at'], 'zkt_logs_self_punch_device_idx');
        });
    }

    public function down(): void
    {
        Schema::table('zkt_attendance_logs', function (Blueprint $table) {
            $table->dropIndex('zkt_logs_self_punch_device_idx');
            $table->dropColumn('client_device_id');
        });
    }
};
