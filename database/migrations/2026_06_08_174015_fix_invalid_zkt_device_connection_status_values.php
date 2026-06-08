<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('zkt_devices')
            ->where('connection_status', 'Unknown')
            ->update(['connection_status' => 'unknown']);

        DB::table('zkt_devices')
            ->where('connection_status', 'Online')
            ->update(['connection_status' => 'online']);

        DB::table('zkt_devices')
            ->where('connection_status', 'Offline')
            ->update(['connection_status' => 'offline']);
    }

    public function down(): void
    {
        //
    }
};
