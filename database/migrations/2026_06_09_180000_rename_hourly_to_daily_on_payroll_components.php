<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_components')
            ->where('calculation_method', 'hourly')
            ->update(['calculation_method' => 'daily']);
    }

    public function down(): void
    {
        DB::table('payroll_components')
            ->where('calculation_method', 'daily')
            ->update(['calculation_method' => 'hourly']);
    }
};
