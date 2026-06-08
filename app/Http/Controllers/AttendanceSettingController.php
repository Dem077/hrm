<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceDutyPolicyRequest;
use App\Http\Requests\StorePublicHolidayRequest;
use App\Http\Requests\UpdateAttendanceDutyPolicyRequest;
use App\Http\Requests\UpdatePublicHolidayRequest;
use App\Models\AttendanceDutyPolicy;
use App\Models\PublicHoliday;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingController extends Controller
{
    public function index(): Response
    {
        $year = (int) request()->integer('year', now()->year);

        return Inertia::render('AttendanceSettings/Index', [
            'policies' => AttendanceDutyPolicy::ordered()
                ->map(fn (AttendanceDutyPolicy $policy) => $policy->toPresentationArray())
                ->values()
                ->all(),
            'holidays' => PublicHoliday::query()
                ->whereYear('date', $year)
                ->orderBy('date')
                ->get()
                ->map(fn (PublicHoliday $holiday) => $holiday->toPresentationArray()),
            'year' => $year,
            'emptyPolicy' => [
                'effective_from' => now()->toDateString(),
                'duty_start_time' => '09:00',
                'duty_end_time' => '18:00',
                'grace_minutes' => 15,
                'saturday_duty_start_time' => '09:00',
                'saturday_duty_end_time' => '14:00',
                'saturday_grace_minutes' => 15,
            ],
            'emptyHoliday' => [
                'name' => '',
                'date' => now()->toDateString(),
                'notes' => '',
            ],
        ]);
    }

    public function storePolicy(StoreAttendanceDutyPolicyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        AttendanceDutyPolicy::query()->create([
            'effective_from' => $data['effective_from'],
            'duty_start_time' => $data['duty_start_time'].':00',
            'duty_end_time' => $data['duty_end_time'].':00',
            'grace_minutes' => $data['grace_minutes'],
            'saturday_duty_start_time' => $data['saturday_duty_start_time'].':00',
            'saturday_duty_end_time' => $data['saturday_duty_end_time'].':00',
            'saturday_grace_minutes' => $data['saturday_grace_minutes'],
        ]);

        return back()->with('success', 'Duty policy saved. It applies from the effective date onward.');
    }

    public function updatePolicy(UpdateAttendanceDutyPolicyRequest $request, AttendanceDutyPolicy $attendanceDutyPolicy): RedirectResponse
    {
        $data = $request->validated();

        $attendanceDutyPolicy->update([
            'effective_from' => $data['effective_from'],
            'duty_start_time' => $data['duty_start_time'].':00',
            'duty_end_time' => $data['duty_end_time'].':00',
            'grace_minutes' => $data['grace_minutes'],
            'saturday_duty_start_time' => $data['saturday_duty_start_time'].':00',
            'saturday_duty_end_time' => $data['saturday_duty_end_time'].':00',
            'saturday_grace_minutes' => $data['saturday_grace_minutes'],
        ]);

        return back()->with('success', 'Duty policy updated successfully.');
    }

    public function destroyPolicy(AttendanceDutyPolicy $attendanceDutyPolicy): RedirectResponse
    {
        if (AttendanceDutyPolicy::query()->count() <= 1) {
            return back()->with('error', 'At least one duty policy must remain.');
        }

        $attendanceDutyPolicy->delete();

        return back()->with('success', 'Duty policy deleted successfully.');
    }

    public function storeHoliday(StorePublicHolidayRequest $request): RedirectResponse
    {
        PublicHoliday::query()->create($request->validated());

        return back()->with('success', 'Public holiday added successfully.');
    }

    public function updateHoliday(UpdatePublicHolidayRequest $request, PublicHoliday $publicHoliday): RedirectResponse
    {
        $publicHoliday->update($request->validated());

        return back()->with('success', 'Public holiday updated successfully.');
    }

    public function destroyHoliday(PublicHoliday $publicHoliday): RedirectResponse
    {
        $publicHoliday->delete();

        return back()->with('success', 'Public holiday deleted successfully.');
    }
}
