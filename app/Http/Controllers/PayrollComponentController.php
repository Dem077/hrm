<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollComponentRequest;
use App\Http\Requests\UpdatePayrollComponentRequest;
use App\Models\PayrollComponent;
use App\Services\Payroll\PayrollComponentService;
use Illuminate\Http\RedirectResponse;

class PayrollComponentController extends Controller
{
    public function __construct(
        protected PayrollComponentService $payrollComponentService,
    ) {}

    public function store(StorePayrollComponentRequest $request): RedirectResponse
    {
        $component = $this->payrollComponentService->create($request->validated());

        return back()->with('success', $this->payrollComponentService->createSuccessMessage($component));
    }

    public function update(UpdatePayrollComponentRequest $request, PayrollComponent $payrollComponent): RedirectResponse
    {
        $error = $this->payrollComponentService->update($payrollComponent, $request->validated());

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Payroll component updated successfully.');
    }

    public function destroy(PayrollComponent $payrollComponent): RedirectResponse
    {
        $error = $this->payrollComponentService->delete($payrollComponent);

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Payroll component deleted successfully.');
    }
}
