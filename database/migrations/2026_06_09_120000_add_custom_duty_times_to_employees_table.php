<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('uses_custom_duty_times')->default(false)->after('works_saturday');
            $table->time('custom_duty_start_time')->nullable()->after('uses_custom_duty_times');
            $table->time('custom_duty_end_time')->nullable()->after('custom_duty_start_time');
            $table->unsignedSmallInteger('custom_grace_minutes')->nullable()->after('custom_duty_end_time');
            $table->time('custom_saturday_duty_start_time')->nullable()->after('custom_grace_minutes');
            $table->time('custom_saturday_duty_end_time')->nullable()->after('custom_saturday_duty_start_time');
            $table->unsignedSmallInteger('custom_saturday_grace_minutes')->nullable()->after('custom_saturday_duty_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'uses_custom_duty_times',
                'custom_duty_start_time',
                'custom_duty_end_time',
                'custom_grace_minutes',
                'custom_saturday_duty_start_time',
                'custom_saturday_duty_end_time',
                'custom_saturday_grace_minutes',
            ]);
        });
    }
};
