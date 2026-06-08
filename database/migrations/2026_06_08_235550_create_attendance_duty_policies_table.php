<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_duty_policies', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->unique();
            $table->time('duty_start_time');
            $table->time('duty_end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_duty_policies');
    }
};
