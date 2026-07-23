<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStructureGradeRequest;
use App\Http\Requests\UpdateStructureGradeDetailsRequest;
use App\Http\Requests\UpdateStructureGradeRequest;
use App\Models\StructureGrade;
use App\Models\StructureLevel;
use App\Services\CompanyStructure\CompanyStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StructureGradeController extends Controller
{
    public function __construct(
        protected CompanyStructureService $companyStructureService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        return Inertia::render('CompanyStructure/Designations', [
            'designations' => $this->companyStructureService->designationsPayload($search ?: null),
            'filters' => [
                'q' => $search,
            ],
            'can_edit' => $request->user()?->can('company-structure.update') ?? false,
        ]);
    }

    public function store(StoreStructureGradeRequest $request, StructureLevel $structure_level): RedirectResponse
    {
        $this->companyStructureService->createGrade($structure_level, $request->validated());

        return back()->with('success', 'Designation created successfully.');
    }

    public function update(UpdateStructureGradeRequest $request, StructureGrade $structure_grade): RedirectResponse
    {
        $this->companyStructureService->updateGrade($structure_grade, $request->validated());

        return back()->with('success', 'Designation updated successfully.');
    }

    public function updateDetails(
        UpdateStructureGradeDetailsRequest $request,
        StructureGrade $structure_grade,
    ): RedirectResponse {
        $this->companyStructureService->updateGrade($structure_grade, $request->validated());

        return back()->with('success', 'Designation details updated successfully.');
    }

    public function destroy(StructureGrade $structure_grade): RedirectResponse
    {
        $this->companyStructureService->deleteGrade($structure_grade);

        return back()->with('success', 'Designation deleted successfully.');
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

        return back()->with('success', 'Designations reordered successfully.');
    }
}
