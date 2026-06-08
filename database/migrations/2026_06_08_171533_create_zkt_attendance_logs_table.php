<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkt_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zkt_device_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('device_uid');
            $table->string('device_user_id');
            $table->unsignedTinyInteger('punch_state')->default(0);
            $table->unsignedTinyInteger('punch_type')->nullable();
            $table->timestamp('punched_at');
            $table->timestamps();

            $table->unique(
                ['zkt_device_id', 'device_uid', 'punched_at'],
                'zkt_attendance_unique_punch'
            );
            $table->index(['zkt_device_id', 'punched_at']);
            $table->index('device_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkt_attendance_logs');
    }
};
