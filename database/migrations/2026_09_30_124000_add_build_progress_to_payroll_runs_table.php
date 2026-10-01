<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->string('build_status', 20)->default('idle')->after('status');
            $table->string('build_action', 20)->nullable()->after('build_status');
            $table->unsignedInteger('build_total')->default(0)->after('build_action');
            $table->unsignedInteger('build_processed')->default(0)->after('build_total');
            $table->string('build_message')->nullable()->after('build_processed');
            $table->boolean('build_cancel_requested')->default(false)->after('build_message');
            $table->timestamp('build_started_at')->nullable()->after('build_cancel_requested');
            $table->timestamp('build_finished_at')->nullable()->after('build_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn([
                'build_status',
                'build_action',
                'build_total',
                'build_processed',
                'build_message',
                'build_cancel_requested',
                'build_started_at',
                'build_finished_at',
            ]);
        });
    }
};
