<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_door_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zkt_device_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(150);
            $table->unsignedInteger('max_accuracy_meters')->default(250);
            $table->json('allowed_public_ips')->nullable();
            $table->boolean('require_public_ip')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_remote_door_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('remote_door_site_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'remote_door_site_id'], 'employee_remote_door_site_unique');
        });

        Schema::create('remote_door_open_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remote_door_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zkt_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zkt_adms_command_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_meters')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('result_message')->nullable();
            $table->timestamp('opened_at');
            $table->timestamps();

            $table->index(['employee_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_door_open_logs');
        Schema::dropIfExists('employee_remote_door_site');
        Schema::dropIfExists('remote_door_sites');
    }
};
