<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('self_punch_sites')) {
            return;
        }

        // Phone GPS commonly reports ~100–150m accuracy indoors.
        DB::table('self_punch_sites')
            ->where('max_accuracy_meters', '<=', 100)
            ->update(['max_accuracy_meters' => 250]);

        DB::table('self_punch_sites')
            ->where('radius_meters', '<=', 100)
            ->update(['radius_meters' => 150]);
    }

    public function down(): void
    {
        //
    }
};
