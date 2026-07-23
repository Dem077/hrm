<?php

namespace Database\Seeders;

use App\Enums\PayrollComponentCalculationMethod;
use App\Enums\PayrollComponentType;
use App\Models\PayrollComponent;
use App\Models\StructureGrade;
use App\Services\Payroll\GradePayrollService;
use Illuminate\Database\Seeder;

/**
 * Builds a usable dummy payroll package on all active grades.
 * Creates optional DEMO_* components; does not delete existing components.
 */
class DummyPayrollStructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureDemoComponents();
        $this->tuneSystemComponents();

        $grades = StructureGrade::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($grades->isEmpty()) {
            $this->command?->warn('No active grades found. Run CompanyStructureSeeder first.');

            return;
        }

        $service = app(GradePayrollService::class);
        $service->backfillMissingMandatoryComponents();

        $basic = PayrollComponent::query()->where('code', PayrollComponent::BASIC_SALARY_CODE)->firstOrFail();
        $attendance = PayrollComponent::query()->where('code', PayrollComponent::ATTENDANCE_ALLOWANCE_CODE)->firstOrFail();
        $pension = PayrollComponent::query()->where('name', 'Pension Fee')->first();
        $hra = PayrollComponent::query()->where('code', 'demo_hra')->firstOrFail();
        $transport = PayrollComponent::query()->where('code', 'demo_transport')->firstOrFail();
        $phone = PayrollComponent::query()->where('code', 'demo_phone')->firstOrFail();
        $loan = PayrollComponent::query()->where('code', 'demo_staff_loan')->firstOrFail();

        foreach ($grades as $index => $grade) {
            $tier = $index % 6;
            $basicAmount = 9000 + ($tier * 3500) + (($index % 3) * 500);
            $attendanceRate = 50 + ($tier * 25);
            $hraAmount = (int) round($basicAmount * 0.15);
            $transportAmount = 400 + ($tier * 150);
            $phoneAmount = 150 + ($tier * 50);

            $items = [
                ['payroll_component_id' => $basic->id, 'amount' => $basicAmount],
                ['payroll_component_id' => $attendance->id, 'amount' => $attendanceRate],
                ['payroll_component_id' => $hra->id, 'amount' => $hraAmount],
                ['payroll_component_id' => $transport->id, 'amount' => $transportAmount],
                ['payroll_component_id' => $phone->id, 'amount' => $phoneAmount],
            ];

            if ($pension) {
                $items[] = ['payroll_component_id' => $pension->id, 'amount' => 0];
            }

            // Attach a sample loan on every 3rd grade.
            if ($index % 3 === 0) {
                $items[] = [
                    'payroll_component_id' => $loan->id,
                    'amount' => 1500 + ($tier * 250),
                    'loan_months' => 12 + ($tier * 2),
                    'loan_bank' => ['BML', 'MIB', 'CBM'][$index % 3],
                ];
            }

            $service->syncPayrollItems($grade, $items);
        }

        $this->command?->info('Dummy payroll structure applied to '.$grades->count().' grades.');
    }

    protected function ensureDemoComponents(): void
    {
        $defs = [
            [
                'code' => 'demo_hra',
                'name' => 'Demo HRA',
                'type' => PayrollComponentType::Addition,
                'calculation_method' => PayrollComponentCalculationMethod::Fixed,
                'sort_order' => 20,
            ],
            [
                'code' => 'demo_transport',
                'name' => 'Demo Transport Allowance',
                'type' => PayrollComponentType::Addition,
                'calculation_method' => PayrollComponentCalculationMethod::Fixed,
                'sort_order' => 21,
            ],
            [
                'code' => 'demo_phone',
                'name' => 'Demo Phone Allowance',
                'type' => PayrollComponentType::Addition,
                'calculation_method' => PayrollComponentCalculationMethod::Fixed,
                'sort_order' => 22,
            ],
            [
                'code' => 'demo_staff_loan',
                'name' => 'Demo Staff Loan',
                'type' => PayrollComponentType::Loan,
                'calculation_method' => PayrollComponentCalculationMethod::Fixed,
                'sort_order' => 80,
            ],
        ];

        foreach ($defs as $def) {
            PayrollComponent::query()->updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'type' => $def['type'],
                    'calculation_method' => $def['calculation_method'],
                    'is_mandatory' => false,
                    'sort_order' => $def['sort_order'],
                    'is_active' => true,
                    'global_rate' => null,
                    'calculation_formula' => null,
                    'applicability_rules' => null,
                ],
            );
        }
    }

    protected function tuneSystemComponents(): void
    {
        PayrollComponent::query()
            ->where('code', PayrollComponent::LATE_FINE_CODE)
            ->update([
                'calculation_method' => PayrollComponentCalculationMethod::PerLateMinuteOfBasic->value,
                'global_rate' => 0.01,
            ]);

        PayrollComponent::query()
            ->where('code', PayrollComponent::ABSENT_FEE_CODE)
            ->update([
                'calculation_method' => PayrollComponentCalculationMethod::CustomFormula->value,
                'calculation_formula' => 'basic_salary/30*absent_days',
                'global_rate' => null,
            ]);

        PayrollComponent::query()
            ->where('code', PayrollComponent::OVERTIME_CODE)
            ->update([
                'calculation_method' => PayrollComponentCalculationMethod::CustomFormula->value,
                'calculation_formula' => 'overtime_hours*(basic_salary/working_days/8)',
                'global_rate' => null,
            ]);

        PayrollComponent::query()
            ->where('code', PayrollComponent::ATTENDANCE_ALLOWANCE_CODE)
            ->update([
                'calculation_method' => PayrollComponentCalculationMethod::Daily->value,
                'calculation_formula' => null,
            ]);
    }
}
