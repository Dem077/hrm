<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('duty_start_time');
            $table->time('duty_end_time');
            $table->unsignedSmallInteger('grace_minutes')->default(15);
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_shift_templates');
    }
};
