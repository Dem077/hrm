<?php

use App\Enums\PayrollComponentCalculationMethod;
use App\Models\PayrollComponent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Absent fee was previously stored as per_absent_day while meaning % of basic.
        DB::table('payroll_components')
            ->where('code', PayrollComponent::ABSENT_FEE_CODE)
            ->where('calculation_method', PayrollComponentCalculationMethod::PerAbsentDay->value)
            ->update([
                'calculation_method' => PayrollComponentCalculationMethod::PerAbsentDayOfBasic->value,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('payroll_components')
            ->where('code', PayrollComponent::ABSENT_FEE_CODE)
            ->where('calculation_method', PayrollComponentCalculationMethod::PerAbsentDayOfBasic->value)
            ->update([
                'calculation_method' => PayrollComponentCalculationMethod::PerAbsentDay->value,
                'updated_at' => now(),
            ]);
    }
};
