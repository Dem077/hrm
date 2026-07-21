<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkt_devices', function (Blueprint $table) {
            $table->string('connection_mode', 20)->default('tcp_pull')->after('protocol');
            $table->timestamp('last_adms_seen_at')->nullable()->after('last_synced_at');
        });

        // Deduplicate empty serial numbers before unique index.
        DB::table('zkt_devices')->where('serial_number', '')->update(['serial_number' => null]);

        Schema::table('zkt_devices', function (Blueprint $table) {
            $table->unique('serial_number');
        });

        Schema::create('zkt_adms_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zkt_device_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('command_no');
            $table->text('payload');
            $table->string('status', 20)->default('pending');
            $table->text('result')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['zkt_device_id', 'command_no']);
            $table->index(['zkt_device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkt_adms_commands');

        Schema::table('zkt_devices', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->dropColumn(['connection_mode', 'last_adms_seen_at']);
        });
    }
};
