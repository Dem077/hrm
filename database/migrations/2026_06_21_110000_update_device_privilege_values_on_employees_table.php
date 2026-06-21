<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->where('device_privilege', 'admin')
            ->update(['device_privilege' => 'administrator']);
    }

    public function down(): void
    {
        DB::table('employees')
            ->where('device_privilege', 'administrator')
            ->update(['device_privilege' => 'admin']);
    }
};
