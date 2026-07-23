<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApprovalTemplateRequest;
use App\Http\Requests\StoreAttendanceDutyPolicyRequest;
use App\Http\Requests\StorePublicHolidayRequest;
use App\Http\Requests\UpdateApprovalTemplateRequest;
use App\Http\Requests\UpdateAttendanceDutyPolicyRequest;
use App\Http\Requests\UpdateCompanyDefaultApprovalTemplatesRequest;
use App\Http\Requests\UpdateLeaveApprovalWorkflowRequest;
use App\Http\Requests\UpdatePayrollPeriodRequest;
use App\Http\Requests\UpdatePublicHolidayRequest;
use App\Enums\ApprovalWorkflowKind;
use App\Models\AppSetting;
use App\Models\ApprovalTemplate;
use App\Models\AttendanceDutyPolicy;
use App\Models\AttendanceGeneralSetting;
use App\Models\Bank;
use App\Models\PublicHoliday;
use App\Services\Attendance\PayrollPeriodService;
use App\Services\Leave\LeaveApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingController extends Controller
{
    public function index(PayrollPeriodService $payrollPeriodService, LeaveApprovalWorkflowService $leaveApprovalWorkflowService): Response
    {
        $year = (int) request()->integer('year', now()->year);
        $settings = AttendanceGeneralSetting::current();

        return Inertia::render('AttendanceSettings/Index', [
            'leaveCarryForwardEnabled' => AppSetting::current()->leave_carry_forward_enabled,
            'approvalTemplates' => $leaveApprovalWorkflowService->templatesPresentation(),
            'banks' => Bank::query()
                ->ordered()
                ->get()
                ->map(fn (Bank $bank) => [
                    'id' => $bank->id,
                    'code' => $bank->code,
                    'name' => $bank->name,
                    'sort_order' => $bank->sort_order,
                    'is_active' => $bank->is_active,
                    'in_use' => $bank->isInUse(),
                ])
                ->values()
                ->all(),
            'payrollPeriod' => [
                'payroll_period_start_day' => $settings->payroll_period_start_day,
                'payroll_period_end_day' => $settings->payroll_period_start_day > 1
                    ? $settings->payroll_period_start_day - 1
                    : null,
                ...$payrollPeriodService->presentation(),
            ],
            'policies' => AttendanceDutyPolicy::permanentOrdered()
                ->map(fn (AttendanceDutyPolicy $policy) => $policy->toPresentationArray())
                ->values()
                ->all(),
            'tempPolicies' => AttendanceDutyPolicy::temporaryOrdered()
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
                'is_temporary' => false,
                'name' => '',
                'effective_from' => now()->toDateString(),
                'effective_until' => null,
                'duty_start_time' => '09:00',
                'duty_end_time' => '18:00',
                'grace_minutes' => 15,
                'saturday_duty_start_time' => '09:00',
                'saturday_duty_end_time' => '14:00',
                'saturday_grace_minutes' => 15,
            ],
            'emptyTempPolicy' => [
                'is_temporary' => true,
                'name' => '',
                'effective_from' => now()->toDateString(),
                'effective_until' => now()->addWeek()->toDateString(),
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

    public function updatePayrollPeriod(UpdatePayrollPeriodRequest $request): RedirectResponse
    {
        AttendanceGeneralSetting::current()->update($request->validated());

        return back()->with('success', 'Payroll period updated successfully.');
    }

    public function updateLeaveCarryForward(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'leave_carry_forward_enabled' => ['required', 'in:0,1'],
        ]);

        AppSetting::current()->update([
            'leave_carry_forward_enabled' => $validated['leave_carry_forward_enabled'] === '1',
        ]);

        return back()->with('success', 'Leave carry-forward setting updated successfully.');
    }

    public function updateLeaveApprovalWorkflow(
        UpdateLeaveApprovalWorkflowRequest $request,
        LeaveApprovalWorkflowService $leaveApprovalWorkflowService,
    ): RedirectResponse {
        $leaveApprovalWorkflowService->saveDefault(ApprovalWorkflowKind::Leave, $request->validated('steps'));

        return back()->with('success', 'Company default leave approval workflow updated successfully.');
    }

    public function storeApprovalTemplate(
        StoreApprovalTemplateRequest $request,
        LeaveApprovalWorkflowService $leaveApprovalWorkflowService,
    ): RedirectResponse {
        $data = $request->validated();
        $kind = ApprovalWorkflowKind::from($data['kind']);

        $leaveApprovalWorkflowService->createTemplate(
            $kind,
            $data['name'],
            $data['description'] ?? null,
            $data['steps'],
        );

        return back()->with('success', "{$kind->label()} approval template created.");
    }

    public function updateApprovalTemplate(
        UpdateApprovalTemplateRequest $request,
        ApprovalTemplate $approvalTemplate,
        LeaveApprovalWorkflowService $leaveApprovalWorkflowService,
    ): RedirectResponse {
        $data = $request->validated();

        $leaveApprovalWorkflowService->updateTemplate(
            $approvalTemplate,
            $data['name'],
            $data['description'] ?? null,
            $data['steps'],
        );

        return back()->with('success', 'Approval template updated.');
    }

    public function destroyApprovalTemplate(
        ApprovalTemplate $approvalTemplate,
        LeaveApprovalWorkflowService $leaveApprovalWorkflowService,
    ): RedirectResponse {
        $leaveApprovalWorkflowService->deleteTemplate($approvalTemplate);

        return back()->with('success', 'Approval template deleted.');
    }

    public function updateCompanyDefaultApprovalTemplates(
        UpdateCompanyDefaultApprovalTemplatesRequest $request,
        LeaveApprovalWorkflowService $leaveApprovalWorkflowService,
    ): RedirectResponse {
        $data = $request->validated();

        $leaveApprovalWorkflowService->setCompanyDefaultTemplate(
            ApprovalWorkflowKind::Leave,
            isset($data['leave_approval_template_id']) ? (int) $data['leave_approval_template_id'] : null,
        );
        $leaveApprovalWorkflowService->setCompanyDefaultTemplate(
            ApprovalWorkflowKind::Overtime,
            isset($data['overtime_approval_template_id']) ? (int) $data['overtime_approval_template_id'] : null,
        );

        return back()->with('success', 'Company default approval templates updated.');
    }

    public function storePolicy(StoreAttendanceDutyPolicyRequest $request): RedirectResponse
    {
        AttendanceDutyPolicy::query()->create($this->policyAttributes($request->validated()));

        $message = $request->boolean('is_temporary')
            ? 'Temporary duty policy saved. It overrides permanent policies for that period.'
            : 'Duty policy saved. It applies from the effective date onward.';

        return back()->with('success', $message);
    }

    public function updatePolicy(UpdateAttendanceDutyPolicyRequest $request, AttendanceDutyPolicy $attendanceDutyPolicy): RedirectResponse
    {
        $attendanceDutyPolicy->update($this->policyAttributes($request->validated()));

        return back()->with('success', 'Duty policy updated successfully.');
    }

    public function destroyPolicy(AttendanceDutyPolicy $attendanceDutyPolicy): RedirectResponse
    {
        if (! $attendanceDutyPolicy->isTemporary() && AttendanceDutyPolicy::query()->permanent()->count() <= 1) {
            return back()->with('error', 'At least one permanent duty policy must remain.');
        }

        $attendanceDutyPolicy->delete();

        return back()->with('success', 'Duty policy deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function policyAttributes(array $data): array
    {
        $isTemporary = (bool) ($data['is_temporary'] ?? false);

        return [
            'effective_from' => $data['effective_from'],
            'effective_until' => $isTemporary ? $data['effective_until'] : null,
            'name' => $isTemporary ? ($data['name'] ?? null) : null,
            'duty_start_time' => $data['duty_start_time'].':00',
            'duty_end_time' => $data['duty_end_time'].':00',
            'grace_minutes' => $data['grace_minutes'],
            'saturday_duty_start_time' => $data['saturday_duty_start_time'].':00',
            'saturday_duty_end_time' => $data['saturday_duty_end_time'].':00',
            'saturday_grace_minutes' => $data['saturday_grace_minutes'],
        ];
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
