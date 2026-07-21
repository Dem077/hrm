<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkt_devices', function (Blueprint $table) {
            $table->unsignedTinyInteger('default_access_group')
                ->default(1)
                ->after('machine_type');
        });
    }

    public function down(): void
    {
        Schema::table('zkt_devices', function (Blueprint $table) {
            $table->dropColumn('default_access_group');
        });
    }
};
