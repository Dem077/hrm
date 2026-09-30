<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grade_payroll_component') && Schema::hasTable('payroll_components')) {
            $loanComponentIds = DB::table('payroll_components')
                ->where('type', 'loan')
                ->pluck('id');

            if ($loanComponentIds->isNotEmpty()) {
                DB::table('grade_payroll_component')
                    ->whereIn('payroll_component_id', $loanComponentIds)
                    ->delete();

                DB::table('payroll_components')
                    ->whereIn('id', $loanComponentIds)
                    ->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('designation_payroll_component') && Schema::hasTable('payroll_components')) {
            $loanComponentIds = DB::table('payroll_components')
                ->where('type', 'loan')
                ->pluck('id');

            if ($loanComponentIds->isNotEmpty()) {
                DB::table('designation_payroll_component')
                    ->whereIn('payroll_component_id', $loanComponentIds)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Loan assignments on designations are not restored.
    }
};
