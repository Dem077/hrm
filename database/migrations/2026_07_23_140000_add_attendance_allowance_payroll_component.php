<?php

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $exists = DB::table('payroll_components')->where('code', 'attendance_allowance')->exists();

        if (! $exists) {
            DB::table('payroll_components')->insert([
                'name' => 'Attendance Allowance',
                'code' => 'attendance_allowance',
                'type' => PayrollComponentType::Addition->value,
                'calculation_method' => PayrollComponentCalculationMethod::Daily->value,
                'global_rate' => 0,
                'calculation_formula' => null,
                'is_mandatory' => true,
                'sort_order' => 35,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $componentId = DB::table('payroll_components')->where('code', 'attendance_allowance')->value('id');
        $gradeIds = DB::table('structure_grades')->pluck('id');

        if (! $componentId) {
            return;
        }

        foreach ($gradeIds as $gradeId) {
            $pivotExists = DB::table('grade_payroll_component')
                ->where('structure_grade_id', $gradeId)
                ->where('payroll_component_id', $componentId)
                ->exists();

            if (! $pivotExists) {
                DB::table('grade_payroll_component')->insert([
                    'structure_grade_id' => $gradeId,
                    'payroll_component_id' => $componentId,
                    'amount' => 0,
                    'loan_months' => null,
                    'loan_bank' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $componentId = DB::table('payroll_components')->where('code', 'attendance_allowance')->value('id');

        if (! $componentId) {
            return;
        }

        DB::table('grade_payroll_component')->where('payroll_component_id', $componentId)->delete();
        DB::table('payroll_components')->where('id', $componentId)->delete();
    }
};
