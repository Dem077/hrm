<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('zkt_devices')
            ->where('name', 'Self Punch')
            ->update([
                'name' => 'Mobile Punch',
                'notes' => 'System device for employee mobile punches from the web app.',
            ]);
    }

    public function down(): void
    {
        DB::table('zkt_devices')
            ->where('name', 'Mobile Punch')
            ->update([
                'name' => 'Self Punch',
                'notes' => 'System device for employee self punches from the web app.',
            ]);
    }
};
