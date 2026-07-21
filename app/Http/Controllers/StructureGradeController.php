<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStructureGradeRequest;
use App\Http\Requests\UpdateStructureGradeRequest;
use App\Models\StructureGrade;
use App\Models\StructureLevel;
use App\Services\CompanyStructure\CompanyStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StructureGradeController extends Controller
{
    public function __construct(
        protected CompanyStructureService $companyStructureService,
    ) {}

    public function store(StoreStructureGradeRequest $request, StructureLevel $structure_level): RedirectResponse
    {
        $this->companyStructureService->createGrade($structure_level, $request->validated());

        return back()->with('success', 'Grade created successfully.');
    }

    public function update(UpdateStructureGradeRequest $request, StructureGrade $structure_grade): RedirectResponse
    {
        $this->companyStructureService->updateGrade($structure_grade, $request->validated());

        return back()->with('success', 'Grade updated successfully.');
    }

    public function destroy(StructureGrade $structure_grade): RedirectResponse
    {
        $this->companyStructureService->deleteGrade($structure_grade);

        return back()->with('success', 'Grade deleted successfully.');
    }

    public function reorder(Request $request, StructureLevel $structure_level): RedirectResponse
    {
        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:structure_grades,id'],
        ]);

        $this->companyStructureService->reorderGrades(
            $structure_level,
            array_map('intval', $validated['ordered_ids']),
        );

        return back()->with('success', 'Grades reordered successfully.');
    }
}
