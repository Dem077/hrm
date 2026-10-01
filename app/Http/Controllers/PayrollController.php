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
use App\Services\Payroll\PayrollJobProgress;
use App\Services\Payroll\PayrollRunService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

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
        $detail = $payrollRunService->detailPayload(
            $payroll_run,
            $request->string('q')->toString() ?: null,
            $request->string('bank')->toString() ?: null,
            $request->string('department')->toString() ?: null,
        );

        return Inertia::render('Payroll/Show', [
            'selectedRun' => $detail['run'],
            'rows' => $detail['rows'],
            'bankTotals' => $detail['bank_totals'],
            'filterOptions' => $detail['filter_options'],
            'can_edit' => $detail['can_edit'],
            'filters' => [
                'q' => $request->string('q')->toString(),
                'bank' => $request->string('bank')->toString(),
                'department' => $request->string('department')->toString(),
            ],
        ]);
    }

    public function matchingEmployeeIds(
        Request $request,
        PayrollRun $payroll_run,
        PayrollRunService $payrollRunService,
    ) {
        $ids = $payrollRunService->matchingEmployeeIds(
            $payroll_run,
            $request->string('q')->toString() ?: null,
            $request->string('bank')->toString() ?: null,
            $request->string('department')->toString() ?: null,
        );

        return response()->json([
            'employee_ids' => $ids,
            'count' => count($ids),
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

        $result = $request->input('period_source') === 'custom'
            ? $payrollRunService->queueCreateFromCustomPeriod(
                Carbon::parse((string) $request->input('from')),
                Carbon::parse((string) $request->input('to')),
                $user,
            )
            : $payrollRunService->queueCreateFromGlobalPeriod($user);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return redirect()
            ->route('payroll.show', $result['payroll_run_id'])
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

        $result = $payrollRunService->queueBulkAdjustments(
            $payroll_run,
            $employeeIds,
            (string) $request->input('type'),
            (string) $request->input('title'),
            (float) $request->input('amount'),
            $request->input('remarks'),
            $user,
            $request,
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return redirect()
            ->route('payroll.show', $payroll_run)
            ->with('success', 'Bulk adjustment queued.');
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

        $result = $payrollRunService->queueProcess($payroll_run, $user);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

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

        $result = $payrollRunService->queueRerun($payroll_run, $user);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return redirect()
            ->route('payroll.show', $payroll_run)
            ->with('success', 'Payroll data refreshed from the latest attendance and structure.');
    }

    public function jobStatus(string $jobId, PayrollJobProgress $payrollJobProgress)
    {
        $progress = $payrollJobProgress->get($jobId);

        if ($progress === null) {
            abort(404, 'Payroll job not found.');
        }

        return response()->json($progress);
    }

    public function cancelJob(string $jobId, PayrollJobProgress $payrollJobProgress)
    {
        $progress = $payrollJobProgress->get($jobId);

        if ($progress === null) {
            abort(404, 'Payroll job not found.');
        }

        $cancelled = $payrollJobProgress->requestCancel($jobId);

        return response()->json([
            'ok' => $cancelled,
            'job' => $payrollJobProgress->get($jobId),
        ]);
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

    public function export(PayrollRun $payroll_run, PayrollRunService $payrollRunService, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $result = $payrollRunService->queueExport($payroll_run, $user);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return redirect()
            ->route('payroll.show', $payroll_run)
            ->with('success', 'Payroll export queued.');
    }

    public function downloadJob(string $jobId, PayrollJobProgress $payrollJobProgress, Request $request)
    {
        $progress = $payrollJobProgress->get($jobId);

        if ($progress === null || (int) ($progress['user_id'] ?? 0) !== (int) $request->user()?->id) {
            abort(404, 'Payroll job not found.');
        }

        if (($progress['status'] ?? null) !== PayrollJobProgress::STATUS_COMPLETED || ! ($progress['download_ready'] ?? false)) {
            abort(409, 'Payroll export is not ready yet.');
        }

        $path = $payrollJobProgress->fileAbsolutePath($jobId);

        if ($path === null) {
            abort(404, 'Payroll export file not found.');
        }

        $filename = (string) ($progress['filename'] ?? 'payroll-export.xlsx');

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
