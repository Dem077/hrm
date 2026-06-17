<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Models\AppSetting;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('LeaveTypes/Index', [
            'leaveTypes' => LeaveType::query()
                ->withCount('leaveRequests')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (LeaveType $leaveType) => $leaveType->toPresentationArray()),
            'emptyLeaveType' => $this->emptyLeaveType(),
            'carryForwardEnabled' => AppSetting::current()->leave_carry_forward_enabled,
        ]);
    }

    public function store(StoreLeaveTypeRequest $request): RedirectResponse
    {
        LeaveType::query()->create($request->validated());

        return back()->with('success', 'Leave type created successfully.');
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($request->validated());

        return back()->with('success', 'Leave type updated successfully.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        if ($leaveType->leaveRequests()->exists()) {
            return back()->with('error', 'This leave type has requests and cannot be deleted. Deactivate it instead.');
        }

        $leaveType->delete();

        return back()->with('success', 'Leave type deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyLeaveType(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'description' => '',
            'requires_document' => false,
            'is_visible_to_employees' => true,
            'is_active' => true,
            'sort_order' => 0,
            'annual_limit' => null,
            'can_carry_forward' => false,
            'max_carry_forward_days' => null,
        ];
    }
}
