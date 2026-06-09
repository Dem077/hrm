<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignationRequest;
use App\Http\Requests\UpdateDesignationRequest;
use App\Models\Designation;
use App\Services\Payroll\DesignationPayrollService;
use Illuminate\Http\RedirectResponse;

class DesignationController extends Controller
{
    public function __construct(
        protected DesignationPayrollService $designationPayrollService,
    ) {}

    public function store(StoreDesignationRequest $request): RedirectResponse
    {
        $this->designationPayrollService->create(
            $request->safe()->only(['name', 'code', 'description', 'sort_order', 'is_active']),
            $request->validated('items'),
        );

        return back()->with('success', 'Designation created successfully.');
    }

    public function update(UpdateDesignationRequest $request, Designation $designation): RedirectResponse
    {
        $this->designationPayrollService->update(
            $designation,
            $request->safe()->only(['name', 'code', 'description', 'sort_order', 'is_active']),
            $request->validated('items'),
        );

        return back()->with('success', 'Designation updated successfully.');
    }

    public function destroy(Designation $designation): RedirectResponse
    {
        $this->designationPayrollService->delete($designation);

        return back()->with('success', 'Designation deleted successfully.');
    }
}
