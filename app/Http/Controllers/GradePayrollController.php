<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGradePayrollRequest;
use App\Models\StructureGrade;
use App\Services\Payroll\GradePayrollService;
use Illuminate\Http\RedirectResponse;

class GradePayrollController extends Controller
{
    public function __construct(
        protected GradePayrollService $gradePayrollService,
    ) {}

    public function update(UpdateGradePayrollRequest $request, StructureGrade $structure_grade): RedirectResponse
    {
        $this->gradePayrollService->updatePackage(
            $structure_grade,
            $request->validated('items'),
        );

        return back()->with('success', 'Grade payroll package updated successfully.');
    }
}
