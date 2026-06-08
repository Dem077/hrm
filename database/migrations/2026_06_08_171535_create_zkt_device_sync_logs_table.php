<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkt_device_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zkt_device_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('records_fetched')->default(0);
            $table->unsignedInteger('records_stored')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['zkt_device_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkt_device_sync_logs');
    }
};
