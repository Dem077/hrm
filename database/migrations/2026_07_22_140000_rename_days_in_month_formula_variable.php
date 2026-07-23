<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_components')
            ->whereNotNull('calculation_formula')
            ->orderBy('id')
            ->each(function (object $component): void {
                $formula = (string) $component->calculation_formula;
                $updated = preg_replace('/\bdays_in_month\b/', 'total_days_of_payroll', $formula);

                if ($updated !== null && $updated !== $formula) {
                    DB::table('payroll_components')
                        ->where('id', $component->id)
                        ->update(['calculation_formula' => $updated]);
                }
            });
    }

    public function down(): void
    {
        DB::table('payroll_components')
            ->whereNotNull('calculation_formula')
            ->orderBy('id')
            ->each(function (object $component): void {
                $formula = (string) $component->calculation_formula;
                $updated = preg_replace('/\btotal_days_of_payroll\b/', 'days_in_month', $formula);

                if ($updated !== null && $updated !== $formula) {
                    DB::table('payroll_components')
                        ->where('id', $component->id)
                        ->update(['calculation_formula' => $updated]);
                }
            });
    }
};
