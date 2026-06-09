<?php

namespace App\Services\Payroll;

class PayrollStructureService
{
    public function __construct(
        protected PayrollComponentService $payrollComponentService,
        protected DesignationPayrollService $designationPayrollService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(): array
    {
        return [
            'components' => $this->payrollComponentService->listForIndex(),
            'designations' => $this->designationPayrollService->listForIndex(),
            'emptyComponent' => $this->payrollComponentService->emptyAttributes(),
            'emptyDesignation' => $this->designationPayrollService->emptyAttributes(),
            'defaultDesignationItems' => $this->designationPayrollService->defaultItemsForNewDesignation(),
        ];
    }
}
