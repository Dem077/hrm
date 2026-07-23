<?php

namespace App\Services\Payroll;

use App\Enums\EmploymentType;
use App\Enums\PayrollApplicabilityField;
use App\Enums\PayrollApplicabilityOperator;
use App\Models\Bank;
use App\Models\Nationality;

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
     *     loanBanks: list<array{value: string, label: string}>,
     *     employmentTypes: list<array{value: string, label: string}>,
     *     nationalities: list<array{value: string, label: string}>,
     *     applicabilityFields: list<array{value: string, label: string}>,
     *     applicabilityOperators: list<array{value: string, label: string}>
     * }
     */
    public function indexPayload(): array
    {
        return [
            'components' => $this->payrollComponentService->listForIndex(),
            'grades' => $this->gradePayrollService->listForIndex(),
            'emptyComponent' => $this->payrollComponentService->emptyAttributes(),
            'loanBanks' => Bank::options(),
            'employmentTypes' => EmploymentType::options(),
            'nationalities' => Nationality::options(),
            'applicabilityFields' => PayrollApplicabilityField::options(),
            'applicabilityOperators' => PayrollApplicabilityOperator::options(),
        ];
    }
}
