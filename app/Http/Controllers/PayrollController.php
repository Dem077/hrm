<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReopenPayrollRunRequest;
use App\Http\Requests\StoreBulkPayrollRunAdjustmentRequest;
use App\Http\Requests\StorePayrollRunAdjustmentRequest;
use App\Http\Requests\StorePayrollRunRequest;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use App\Services\Attendance\PayrollPeriodService;
use App\Services\Payroll\PayrollRunService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index(PayrollPeriodService $payrollPeriodService): Response
    {
        $runs = PayrollRun::query()
            ->latest('id')
            ->with(['createdBy:id,name', 'processedBy:id,name', 'finalisedBy:id,name'])
            ->get();

        return Inertia::render('Payroll/Index', [
            'runs' => $runs->map(fn (PayrollRun $run) => $this->formatRun($run))->values(),
            'periodPresentation' => $payrollPeriodService->presentation(),
        ]);
    }

    public function show(Request $request, PayrollRun $payroll_run, PayrollRunService $payrollRunService): Response
    {
        $search = $request->string('q')->toString();
        $detail = $payrollRunService->detailPayload($payroll_run, $search);

        return Inertia::render('Payroll/Show', [
            'selectedRun' => $detail['run'],
            'rows' => $detail['rows'],
            'bankTotals' => $detail['bank_totals'],
            'can_edit' => $detail['can_edit'],
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function employeeAttendance(
        PayrollRun $payroll_run,
        Employee $employee,
        PayrollRunService $payrollRunService,
    ): Response {
        $payload = $payrollRunService->employeeAttendancePayload($payroll_run, $employee->id);

        return Inertia::render('Payroll/EmployeeAttendance', $payload);
    }

    public function employeeAdjustments(
        PayrollRun $payroll_run,
        Employee $employee,
        PayrollRunService $payrollRunService,
    ): Response {
        $payload = $payrollRunService->employeeAdjustmentsPayload($payroll_run, $employee->id);

        return Inertia::render('Payroll/EmployeeAdjustments', $payload);
    }

    public function store(StorePayrollRunRequest $request, PayrollRunService $payrollRunService)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $run = $request->input('period_source') === 'custom'
            ? $payrollRunService->createDraftFromCustomPeriod(
                Carbon::parse((string) $request->input('from')),
                Carbon::parse((string) $request->input('to')),
                $user,
            )
            : $payrollRunService->createDraftFromGlobalPeriod($user);

        return redirect()
            ->route('payroll.show', $run)
            ->with('success', 'New payroll draft created.');
    }

    public function storeAdjustment(
        StorePayrollRunAdjustmentRequest $request,
        PayrollRun $payroll_run,
        Employee $employee,
        PayrollRunService $payrollRunService,
    ) {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $payrollRunService->addAdjustment(
            $payroll_run,
            $employee->id,
            (string) $request->input('type'),
            (string) $request->input('title'),
            (float) $request->input('amount'),
            $request->input('remarks'),
            $user,
            $request,
        );

        return redirect()
            ->route('payroll.employees.adjustments', [$payroll_run, $employee])
            ->with('success', 'Adjustment added.');
    }

    public function storeBulkAdjustment(
        StoreBulkPayrollRunAdjustmentRequest $request,
        PayrollRun $payroll_run,
        PayrollRunService $payrollRunService,
    ) {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        /** @var list<int> $employeeIds */
        $employeeIds = array_map('intval', $request->input('employee_ids', []));

        $count = $payrollRunService->addBulkAdjustments(
            $payroll_run,
            $employeeIds,
            (string) $request->input('type'),
            (string) $request->input('title'),
            (float) $request->input('amount'),
            $request->input('remarks'),
            $user,
            $request,
        );

        return redirect()
            ->route('payroll.show', $payroll_run)
            ->with('success', "Adjustment added for {$count} employees.");
    }

    public function destroyAdjustment(
        Request $request,
        PayrollRun $payroll_run,
        PayrollRunAdjustment $adjustment,
        PayrollRunService $payrollRunService,
    ) {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $payrollRunService->deleteAdjustment($payroll_run, $adjustment, $user, $request);

        return redirect()
            ->back()
            ->with('success', 'Adjustment removed.');
    }

    public function process(PayrollRun $payroll_run, PayrollRunService $payrollRunService, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $payrollRunService->process($payroll_run, $user, $request);

        return redirect()
            ->route('payroll.show', $payroll_run)
            ->with('success', 'Payroll run processed.');
    }

    public function rerun(PayrollRun $payroll_run, PayrollRunService $payrollRunService, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $payrollRunService->rerun($payroll_run, $user, $request);

        return redirect()
            ->route('payroll.show', $payroll_run)
            ->with('success', 'Payroll data refreshed from the latest attendance and structure.');
    }

    public function finalize(PayrollRun $payroll_run, PayrollRunService $payrollRunService, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $payrollRunService->finalize($payroll_run, $user, $request);

        return redirect()
            ->route('payroll.show', $payroll_run)
            ->with('success', 'Payroll run finalised.');
    }

    public function reopen(
        ReopenPayrollRunRequest $request,
        PayrollRun $payroll_run,
        PayrollRunService $payrollRunService,
    ) {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return back()->withErrors([
                'password' => 'Password is incorrect.',
            ]);
        }

        $payrollRunService->reopen(
            $payroll_run,
            $user,
            $request,
            $request->input('reason'),
        );

        return redirect()
            ->route('payroll.show', $payroll_run)
            ->with('success', 'Payroll run reopened to draft.');
    }

    public function destroy(PayrollRun $payroll_run, PayrollRunService $payrollRunService)
    {
        $payrollRunService->deleteDraft($payroll_run);

        return redirect()
            ->route('payroll.index')
            ->with('success', 'Draft payroll deleted.');
    }

    public function export(PayrollRun $payroll_run, PayrollRunService $payrollRunService): StreamedResponse
    {
        return $payrollRunService->exportRun($payroll_run);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRun(PayrollRun $run): array
    {
        return [
            'id' => $run->id,
            'reference_no' => $run->reference_no,
            'period_label' => $run->period_label,
            'period_from' => $run->period_from?->toDateString(),
            'period_to' => $run->period_to?->toDateString(),
            'period_source' => $run->period_source,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'created_by' => $run->createdBy?->name,
            'processed_by' => $run->processedBy?->name,
            'finalised_by' => $run->finalisedBy?->name,
            'created_at' => $run->created_at?->toIso8601String(),
            'processed_at' => $run->processed_at?->toIso8601String(),
            'finalised_at' => $run->finalised_at?->toIso8601String(),
        ];
    }
}
