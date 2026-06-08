<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_duty_policies', function (Blueprint $table) {
            $table->dropUnique(['effective_from']);
            $table->string('name')->nullable()->after('effective_from');
            $table->date('effective_until')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_duty_policies', function (Blueprint $table) {
            $table->dropColumn(['name', 'effective_until']);
            $table->unique('effective_from');
        });
    }
};
