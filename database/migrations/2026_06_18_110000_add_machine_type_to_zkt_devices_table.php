<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkt_devices', function (Blueprint $table) {
            $table->string('machine_type', 20)->default('attendance')->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('zkt_devices', function (Blueprint $table) {
            $table->dropColumn('machine_type');
        });
    }
};
