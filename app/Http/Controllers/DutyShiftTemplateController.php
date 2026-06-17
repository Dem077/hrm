<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDutyShiftTemplateRequest;
use App\Http\Requests\UpdateDutyShiftTemplateRequest;
use App\Models\DutyShiftTemplate;
use Illuminate\Http\RedirectResponse;

class DutyShiftTemplateController extends Controller
{
    public function store(StoreDutyShiftTemplateRequest $request): RedirectResponse
    {
        DutyShiftTemplate::query()->create($request->validated());

        return back()->with('success', 'Fixed duty shift created successfully.');
    }

    public function update(UpdateDutyShiftTemplateRequest $request, DutyShiftTemplate $dutyShiftTemplate): RedirectResponse
    {
        $dutyShiftTemplate->update($request->validated());

        return back()->with('success', 'Fixed duty shift updated successfully.');
    }

    public function destroy(DutyShiftTemplate $dutyShiftTemplate): RedirectResponse
    {
        $dutyShiftTemplate->delete();

        return back()->with('success', 'Fixed duty shift deleted successfully.');
    }
}
