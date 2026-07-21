<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStructureNodeRequest;
use App\Http\Requests\UpdateStructureNodeRequest;
use App\Models\StructureGroup;
use App\Models\StructureNode;
use App\Services\CompanyStructure\CompanyStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StructureNodeController extends Controller
{
    public function __construct(
        protected CompanyStructureService $companyStructureService,
    ) {}

    public function store(StoreStructureNodeRequest $request, StructureGroup $structure_group): RedirectResponse
    {
        $this->companyStructureService->createNode($structure_group, $request->validated());

        return back()->with('success', 'Subgroup created successfully.');
    }

    public function update(UpdateStructureNodeRequest $request, StructureNode $structure_node): RedirectResponse
    {
        $this->companyStructureService->updateNode($structure_node, $request->validated());

        return back()->with('success', 'Subgroup updated successfully.');
    }

    public function destroy(StructureNode $structure_node): RedirectResponse
    {
        $this->companyStructureService->deleteNode($structure_node);

        return back()->with('success', 'Subgroup deleted successfully.');
    }

    public function move(Request $request, StructureNode $structure_node): RedirectResponse
    {
        $validated = $request->validate([
            'structure_group_id' => ['required', 'integer', 'exists:structure_groups,id'],
            'parent_id' => ['nullable', 'integer', 'exists:structure_nodes,id'],
        ]);

        $this->companyStructureService->moveNode(
            $structure_node,
            (int) $validated['structure_group_id'],
            isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
        );

        return back()->with('success', 'Subgroup moved successfully.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'exists:structure_nodes,id'],
            'parent_id' => ['nullable', 'integer', 'exists:structure_nodes,id'],
            'structure_group_id' => ['required', 'integer', 'exists:structure_groups,id'],
        ]);

        $this->companyStructureService->reorderNodes(
            isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            (int) $validated['structure_group_id'],
            array_map('intval', $validated['ordered_ids']),
        );

        return back()->with('success', 'Subgroups reordered successfully.');
    }
}
