<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('zkt_devices')
            ->whereIn('machine_type', ['punch_in', 'punch_out'])
            ->update(['machine_type' => 'access']);
    }

    public function down(): void
    {
        // Cannot reliably restore previous punch_in / punch_out values.
    }
};
