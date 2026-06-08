<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_duty_policies', function (Blueprint $table) {
            $table->time('saturday_duty_start_time')->nullable()->after('grace_minutes');
            $table->time('saturday_duty_end_time')->nullable()->after('saturday_duty_start_time');
            $table->unsignedSmallInteger('saturday_grace_minutes')->nullable()->after('saturday_duty_end_time');
        });

        DB::table('attendance_duty_policies')->update([
            'saturday_duty_start_time' => '09:00:00',
            'saturday_duty_end_time' => '14:00:00',
            'saturday_grace_minutes' => 15,
        ]);
    }

    public function down(): void
    {
        Schema::table('attendance_duty_policies', function (Blueprint $table) {
            $table->dropColumn([
                'saturday_duty_start_time',
                'saturday_duty_end_time',
                'saturday_grace_minutes',
            ]);
        });
    }
};
