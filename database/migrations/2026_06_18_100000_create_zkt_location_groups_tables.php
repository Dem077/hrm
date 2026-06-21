<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkt_location_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('zkt_location_group_device', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zkt_location_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zkt_device_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['zkt_location_group_id', 'zkt_device_id'], 'location_group_device_unique');
        });

        Schema::create('employee_zkt_location_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zkt_location_group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'zkt_location_group_id'], 'employee_location_group_unique');
        });

        Schema::create('zkt_device_employee_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zkt_device_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('device_uid')->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'zkt_device_id'], 'device_employee_sync_unique');
            $table->index(['zkt_device_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkt_device_employee_syncs');
        Schema::dropIfExists('employee_zkt_location_group');
        Schema::dropIfExists('zkt_location_group_device');
        Schema::dropIfExists('zkt_location_groups');
    }
};
