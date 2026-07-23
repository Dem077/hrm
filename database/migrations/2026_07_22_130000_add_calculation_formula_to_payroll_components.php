<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->text('calculation_formula')->nullable()->after('global_rate');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->dropColumn('calculation_formula');
        });
    }
};
