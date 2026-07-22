<?php

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->decimal('global_rate', 12, 2)->nullable()->after('calculation_method');
        });

        $now = now();

        $components = [
            [
                'name' => 'Late Fine',
                'code' => 'late_fine',
                'type' => PayrollComponentType::Deduction->value,
                'calculation_method' => PayrollComponentCalculationMethod::PerLateMinute->value,
                'global_rate' => 0,
                'is_mandatory' => true,
                'sort_order' => 90,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Absent Fee',
                'code' => 'absent_fee',
                'type' => PayrollComponentType::Deduction->value,
                'calculation_method' => PayrollComponentCalculationMethod::PerAbsentDay->value,
                'global_rate' => 0,
                'is_mandatory' => true,
                'sort_order' => 91,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($components as $component) {
            $exists = DB::table('payroll_components')->where('code', $component['code'])->exists();

            if (! $exists) {
                DB::table('payroll_components')->insert($component);
            }
        }

        $componentIds = DB::table('payroll_components')
            ->whereIn('code', ['late_fine', 'absent_fee'])
            ->pluck('id');

        $gradeIds = DB::table('structure_grades')->pluck('id');

        foreach ($gradeIds as $gradeId) {
            foreach ($componentIds as $componentId) {
                $exists = DB::table('grade_payroll_component')
                    ->where('structure_grade_id', $gradeId)
                    ->where('payroll_component_id', $componentId)
                    ->exists();

                if (! $exists) {
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
    }

    public function down(): void
    {
        $componentIds = DB::table('payroll_components')
            ->whereIn('code', ['late_fine', 'absent_fee'])
            ->pluck('id');

        if ($componentIds->isNotEmpty()) {
            DB::table('grade_payroll_component')
                ->whereIn('payroll_component_id', $componentIds)
                ->delete();

            DB::table('payroll_components')
                ->whereIn('id', $componentIds)
                ->delete();
        }

        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->dropColumn('global_rate');
        });
    }
};
