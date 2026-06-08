<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkt_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address');
            $table->unsignedSmallInteger('port')->default(4370);
            $table->string('protocol', 10)->default('tcp');
            $table->unsignedInteger('comm_password')->default(0);
            $table->string('serial_number')->nullable();
            $table->string('model_name')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync')->default(true);
            $table->unsignedSmallInteger('sync_interval_minutes')->default(10);
            $table->string('connection_status', 20)->default('unknown');
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('tcpmux_enabled')->default(false);
            $table->string('tcpmux_subdomain')->nullable();
            $table->unsignedSmallInteger('tcpmux_port')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkt_devices');
    }
};
