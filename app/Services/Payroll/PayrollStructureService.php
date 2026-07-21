<?php

namespace App\Services\Payroll;

use App\Models\Bank;

class PayrollStructureService
{
    public function __construct(
        protected PayrollComponentService $payrollComponentService,
        protected GradePayrollService $gradePayrollService,
    ) {}

    /**
     * @return array{
     *     components: list<array<string, mixed>>,
     *     grades: list<array<string, mixed>>,
     *     emptyComponent: array<string, mixed>,
     *     loanBanks: list<array{value: string, label: string}>
     * }
     */
    public function indexPayload(): array
    {
        return [
            'components' => $this->payrollComponentService->listForIndex(),
            'grades' => $this->gradePayrollService->listForIndex(),
            'emptyComponent' => $this->payrollComponentService->emptyAttributes(),
            'loanBanks' => Bank::options(),
        ];
    }
}
