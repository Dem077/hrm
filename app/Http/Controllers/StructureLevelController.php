<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStructureLevelRequest;
use App\Http\Requests\UpdateStructureLevelRequest;
use App\Models\StructureLevel;
use App\Services\CompanyStructure\CompanyStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StructureLevelController extends Controller
{
    public function __construct(
        protected CompanyStructureService $companyStructureService,
    ) {}

    public function store(StoreStructureLevelRequest $request): RedirectResponse
    {
        $this->companyStructureService->createLevel($request->validated());

        return back()->with('success', 'Level created successfully.');
    }

    public function update(UpdateStructureLevelRequest $request, StructureLevel $structure_level): RedirectResponse
    {
        $this->companyStructureService->updateLevel($structure_level, $request->validated());

        return back()->with('success', 'Level updated successfully.');
    }

    public function destroy(StructureLevel $structure_level): RedirectResponse
    {
        $this->companyStructureService->deleteLevel($structure_level);

        return back()->with('success', 'Level deleted successfully.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:structure_levels,id'],
            'structure_group_id' => ['nullable', 'integer', 'exists:structure_groups,id'],
            'structure_node_id' => ['nullable', 'integer', 'exists:structure_nodes,id'],
        ]);

        $this->companyStructureService->reorderLevels(
            isset($validated['structure_group_id']) ? (int) $validated['structure_group_id'] : null,
            isset($validated['structure_node_id']) ? (int) $validated['structure_node_id'] : null,
            array_map('intval', $validated['ordered_ids']),
        );

        return back()->with('success', 'Levels reordered successfully.');
    }
}
