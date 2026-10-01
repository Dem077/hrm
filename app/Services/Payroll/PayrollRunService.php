<?php

namespace App\Services\Payroll;

use App\Enums\PayrollRunStatus;
use App\Exceptions\PayrollJobCancelledException;
use App\Jobs\BuildPayrollRunJob;
use App\Jobs\BulkAdjustPayrollRunJob;
use App\Jobs\ExportPayrollRunJob;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use App\Models\PayrollRunAuditLog;
use App\Models\PayrollRunItem;
use App\Models\User;
use App\Services\Attendance\AttendanceSheetService;
use App\Services\Attendance\PayrollPeriodService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunService
{
    public function __construct(
        private readonly PayrollPeriodService $payrollPeriodService,
        private readonly PayrollProcessingService $payrollProcessingService,
        private readonly AttendanceSheetService $attendanceSheetService,
        private readonly PayrollJobProgress $payrollJobProgress,
    ) {}

    /**
     * @return array{job_id: string, payroll_run_id: int}
     */
    public function queueCreateFromGlobalPeriod(User $user): array
    {
        $period = $this->payrollPeriodService->currentPeriod();

        return $this->queueCreateDraft(
            $period['from'],
            $period['to'],
            $period['label'],
            'global',
            $user,
        );
    }

    /**
     * @return array{job_id: string, payroll_run_id: int}
     */
    public function queueCreateFromCustomPeriod(CarbonInterface $from, CarbonInterface $to, User $user): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return $this->queueCreateDraft(
            $from,
            $to,
            $from->toDateString().' to '.$to->toDateString(),
            'custom',
            $user,
        );
    }

    /**
     * @return array{job_id: string, payroll_run_id: int}
     */
    public function queueProcess(PayrollRun $run, User $actor): array
    {
        if ($run->status !== PayrollRunStatus::Draft) {
            throw ValidationException::withMessages([
                'run' => 'Only draft payroll runs can be processed.',
            ]);
        }

        return $this->queueRebuild($run, $actor, 'process');
    }

    /**
     * @return array{job_id: string, payroll_run_id: int}
     */
    public function queueRerun(PayrollRun $run, User $actor): array
    {
        if (! in_array($run->status, [PayrollRunStatus::Draft, PayrollRunStatus::Processed], true)) {
            throw ValidationException::withMessages([
                'run' => 'Only draft or processed payroll runs can be rerun.',
            ]);
        }

        return $this->queueRebuild($run, $actor, 'rerun');
    }

    public function createDraftFromGlobalPeriod(User $user): PayrollRun
    {
        $period = $this->payrollPeriodService->currentPeriod();

        return $this->createDraft(
            $period['from'],
            $period['to'],
            $period['label'],
            'global',
            $user,
        );
    }

    public function createDraftFromCustomPeriod(CarbonInterface $from, CarbonInterface $to, User $user): PayrollRun
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return $this->createDraft(
            $from,
            $to,
            $from->toDateString().' to '.$to->toDateString(),
            'custom',
            $user,
        );
    }

    public function process(PayrollRun $run, User $actor, Request $request): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Draft) {
            throw ValidationException::withMessages([
                'run' => 'Only draft payroll runs can be processed.',
            ]);
        }

        DB::transaction(function () use ($run, $actor, $request): void {
            $this->rebuildItems($run);
            $run->update([
                'status' => PayrollRunStatus::Processed,
                'processed_by_user_id' => $actor->id,
                'processed_at' => now(),
            ]);
            $this->addAuditLog($run, 'processed', $actor, $request);
        });

        return $run->fresh(['items']);
    }

    public function rerun(PayrollRun $run, User $actor, Request $request): PayrollRun
    {
        if (! in_array($run->status, [PayrollRunStatus::Draft, PayrollRunStatus::Processed], true)) {
            throw ValidationException::withMessages([
                'run' => 'Only draft or processed payroll runs can be rerun.',
            ]);
        }

        DB::transaction(function () use ($run, $actor, $request): void {
            $this->rebuildItems($run);
            $this->addAuditLog($run, 'rerun', $actor, $request, false, [
                'status' => $run->status->value,
            ]);
        });

        return $run->fresh(['items']);
    }

    public function finalize(PayrollRun $run, User $actor, Request $request): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Processed) {
            throw ValidationException::withMessages([
                'run' => 'Only processed payroll runs can be finalised.',
            ]);
        }

        DB::transaction(function () use ($run, $actor, $request): void {
            $run->update([
                'status' => PayrollRunStatus::Finalised,
                'finalised_by_user_id' => $actor->id,
                'finalised_at' => now(),
                'finalised_version' => $run->finalised_version + 1,
            ]);
            $this->addAuditLog($run, 'finalised', $actor, $request);
        });

        return $run->fresh(['items']);
    }

    public function reopen(PayrollRun $run, User $actor, Request $request, ?string $reason = null): PayrollRun
    {
        if ($run->status !== PayrollRunStatus::Finalised) {
            throw ValidationException::withMessages([
                'run' => 'Only finalised payroll runs can be reopened.',
            ]);
        }

        DB::transaction(function () use ($run, $actor, $request, $reason): void {
            $run->update([
                'status' => PayrollRunStatus::Draft,
                'reopened_by_user_id' => $actor->id,
                'reopened_at' => now(),
            ]);
            $this->addAuditLog($run, 'reopened', $actor, $request, true, [
                'reason' => $reason,
            ]);
        });

        return $run->fresh(['items']);
    }

    public function deleteDraft(PayrollRun $run): void
    {
        if ($run->status !== PayrollRunStatus::Draft) {
            throw ValidationException::withMessages([
                'run' => 'Only draft payroll runs can be deleted.',
            ]);
        }

        $run->delete();
    }

    /**
     * @return array{
     *     run: array<string, mixed>,
     *     rows: \Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *     bank_totals: list<array<string, mixed>>,
     *     filter_options: array{banks: list<string>, departments: list<string>},
     *     can_edit: bool
     * }
     */
    public function detailPayload(
        PayrollRun $run,
        ?string $query = null,
        ?string $bank = null,
        ?string $department = null,
        int $perPage = 50,
    ): array {
        $run->loadMissing([
            'createdBy:id,name',
            'processedBy:id,name',
            'finalisedBy:id,name',
            'reopenedBy:id,name',
            'auditLogs.performedBy:id,name',
        ]);

        $itemsBase = PayrollRunItem::query()->where('payroll_run_id', $run->id);

        $totalsRow = (clone $itemsBase)
            ->selectRaw('COALESCE(SUM(gross), 0) as gross, COALESCE(SUM(deductions), 0) as deductions, COALESCE(SUM(net), 0) as net')
            ->first();

        $bankTotals = (clone $itemsBase)
            ->selectRaw("CASE WHEN bank_name IS NULL OR TRIM(bank_name) = '' THEN 'No bank' ELSE bank_name END as bank")
            ->selectRaw('COUNT(*) as employee_count')
            ->selectRaw('COALESCE(SUM(gross), 0) as gross')
            ->selectRaw('COALESCE(SUM(deductions), 0) as deductions')
            ->selectRaw('COALESCE(SUM(net), 0) as net')
            ->groupByRaw("CASE WHEN bank_name IS NULL OR TRIM(bank_name) = '' THEN 'No bank' ELSE bank_name END")
            ->orderBy('bank')
            ->get()
            ->map(fn ($row) => [
                'bank' => (string) $row->bank,
                'employee_count' => (int) $row->employee_count,
                'gross' => round((float) $row->gross, 2),
                'deductions' => round((float) $row->deductions, 2),
                'net' => round((float) $row->net, 2),
            ])
            ->all();

        $banks = (clone $itemsBase)
            ->selectRaw("CASE WHEN bank_name IS NULL OR TRIM(bank_name) = '' THEN 'No bank' ELSE bank_name END as bank")
            ->distinct()
            ->orderBy('bank')
            ->pluck('bank')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();

        $departments = (clone $itemsBase)
            ->selectRaw("CASE WHEN department_name IS NULL OR TRIM(department_name) = '' THEN 'No department' ELSE department_name END as department")
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();

        $filtered = $this->filteredItemsQuery($run->id, $query, $bank, $department)
            ->with('employee:id,bank_name,account_name,account_no');

        $rows = $filtered
            ->paginate(max(10, min(200, $perPage)))
            ->withQueryString()
            ->through(fn (PayrollRunItem $item) => $this->toRowArray($item, $run));

        return [
            'run' => $this->toRunArray($run, [
                'gross' => round((float) ($totalsRow->gross ?? 0), 2),
                'deductions' => round((float) ($totalsRow->deductions ?? 0), 2),
                'net' => round((float) ($totalsRow->net ?? 0), 2),
            ]),
            'rows' => $rows,
            'bank_totals' => $bankTotals,
            'filter_options' => [
                'banks' => $banks,
                'departments' => $departments,
            ],
            'can_edit' => $run->status->isEditable(),
        ];
    }

    /**
     * @return list<int>
     */
    public function matchingEmployeeIds(
        PayrollRun $run,
        ?string $query = null,
        ?string $bank = null,
        ?string $department = null,
    ): array {
        return $this->filteredItemsQuery($run->id, $query, $bank, $department)
            ->orderBy('employee_id')
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\PayrollRunItem>
     */
    protected function filteredItemsQuery(
        int $payrollRunId,
        ?string $query = null,
        ?string $bank = null,
        ?string $department = null,
    ) {
        return PayrollRunItem::query()
            ->where('payroll_run_id', $payrollRunId)
            ->when(filled($query), function ($builder) use ($query): void {
                $needle = '%'.mb_strtolower(trim((string) $query)).'%';
                $builder->where(function ($inner) use ($needle): void {
                    $inner->whereRaw('LOWER(employee_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(staff_id, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(national_id, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(bank_name, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(account_name, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(account_no, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(department_name, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(designation_name, \'\')) LIKE ?', [$needle]);
                });
            })
            ->when(filled($bank), function ($builder) use ($bank): void {
                if ($bank === 'No bank') {
                    $builder->where(function ($inner): void {
                        $inner->whereNull('bank_name')->orWhereRaw("TRIM(bank_name) = ''");
                    });

                    return;
                }

                $builder->where('bank_name', $bank);
            })
            ->when(filled($department), function ($builder) use ($department): void {
                if ($department === 'No department') {
                    $builder->where(function ($inner): void {
                        $inner->whereNull('department_name')->orWhereRaw("TRIM(department_name) = ''");
                    });

                    return;
                }

                $builder->where('department_name', $department);
            })
            ->orderByRaw("CASE WHEN bank_name IS NULL OR TRIM(bank_name) = '' THEN 'No bank' ELSE bank_name END")
            ->orderBy('employee_name');
    }

    /**
     * @return array{run: array<string, mixed>, employee: array<string, mixed>, attendance_rows: list<array<string, mixed>>}
     */
    public function employeeAttendancePayload(PayrollRun $run, int $employeeId): array
    {
        $item = $this->requireRunItem($run, $employeeId);
        $attendanceRows = $this->attendanceSheetService
            ->build($run->period_from, $run->period_to, null, $employeeId)['rows'];

        return [
            'run' => $this->toRunSummaryArray($run),
            'employee' => $this->toEmployeeSummaryArray($item),
            'attendance_rows' => $attendanceRows,
        ];
    }

    /**
     * @return array{run: array<string, mixed>, employee: array<string, mixed>, adjustments: list<array<string, mixed>>, can_edit: bool}
     */
    public function employeeAdjustmentsPayload(PayrollRun $run, int $employeeId): array
    {
        $item = $this->requireRunItem($run, $employeeId);

        $adjustments = PayrollRunAdjustment::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employeeId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PayrollRunAdjustment $adjustment) => [
                'id' => $adjustment->id,
                'title' => $adjustment->title,
                'type' => $adjustment->type,
                'amount' => $adjustment->amount,
                'remarks' => $adjustment->remarks,
                'created_at' => $adjustment->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'run' => $this->toRunSummaryArray($run),
            'employee' => $this->toEmployeeSummaryArray($item),
            'adjustments' => $adjustments,
            'can_edit' => $run->status->isEditable(),
        ];
    }

    public function addAdjustment(
        PayrollRun $run,
        int $employeeId,
        string $type,
        string $title,
        float $amount,
        ?string $remarks,
        User $actor,
        Request $request,
    ): void {
        if (! $run->status->isEditable()) {
            throw ValidationException::withMessages([
                'run' => 'Finalised payroll runs cannot be edited.',
            ]);
        }

        $this->requireRunItem($run, $employeeId);

        DB::transaction(function () use ($run, $employeeId, $type, $title, $amount, $remarks, $actor, $request): void {
            $this->createAdjustmentRecord($run, $employeeId, $type, $title, $amount, $remarks, $actor);
            $this->addAuditLog($run, 'adjustment_added', $actor, $request, false, [
                'employee_id' => $employeeId,
                'type' => $type,
                'title' => $title,
                'amount' => round($amount, 2),
            ]);
        });
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array{job_id: string, payroll_run_id: int}
     */
    public function queueBulkAdjustments(
        PayrollRun $run,
        array $employeeIds,
        string $type,
        string $title,
        float $amount,
        ?string $remarks,
        User $actor,
        ?Request $request = null,
    ): array {
        if (! $run->status->isEditable()) {
            throw ValidationException::withMessages([
                'run' => 'Finalised payroll runs cannot be edited.',
            ]);
        }

        $this->assertRunNotBusy($run->id);

        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if ($employeeIds === []) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Select at least one employee.',
            ]);
        }

        $validIds = PayrollRunItem::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('employee_id', $employeeIds)
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validIds) !== count($employeeIds)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'One or more selected employees are not on this payroll run.',
            ]);
        }

        $lock = Cache::lock("payroll-bulk-adjust:{$run->id}", 30);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'run' => 'A payroll job is already in progress for this run.',
            ]);
        }

        try {
            $this->assertRunNotBusy($run->id);

            $jobId = $this->payrollJobProgress->create('bulk_adjust', $run->id, $actor->id, [
                'total' => count($validIds),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
            $this->markRunBusy($run->id, $jobId);

            BulkAdjustPayrollRunJob::dispatch(
                $jobId,
                $run->id,
                $actor->id,
                $validIds,
                $type,
                $title,
                $amount,
                $remarks,
            );

            return [
                'job_id' => $jobId,
                'payroll_run_id' => $run->id,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<int>  $employeeIds
     */
    public function executeBulkAdjustJob(
        string $jobId,
        int $payrollRunId,
        int $userId,
        array $employeeIds,
        string $type,
        string $title,
        float $amount,
        ?string $remarks,
    ): void {
        $run = PayrollRun::query()->find($payrollRunId);
        $user = User::query()->find($userId);
        $progressMeta = $this->payrollJobProgress->get($jobId) ?? [];

        if (! $run || ! $user) {
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_FAILED,
                'error' => 'Payroll run or user not found.',
                'message' => 'Failed',
            ]);
            $this->clearRunBusy($payrollRunId);

            return;
        }

        if (! $run->status->isEditable()) {
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_FAILED,
                'error' => 'Finalised payroll runs cannot be edited.',
                'message' => 'Failed',
            ]);
            $this->clearRunBusy($payrollRunId);

            return;
        }

        $total = count($employeeIds);
        $done = 0;

        try {
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_RUNNING,
                'total' => $total,
                'done' => 0,
                'message' => 'Starting…',
            ]);

            foreach ($employeeIds as $employeeId) {
                if ($this->payrollJobProgress->isCancelRequested($jobId)) {
                    throw new PayrollJobCancelledException('Payroll job cancelled.');
                }

                $this->createAdjustmentRecord($run, (int) $employeeId, $type, $title, $amount, $remarks, $user);
                $done++;

                $this->payrollJobProgress->update($jobId, [
                    'status' => PayrollJobProgress::STATUS_RUNNING,
                    'done' => $done,
                    'total' => $total,
                    'message' => "Adjusted {$done} of {$total} employees…",
                ]);
            }

            $auditRequest = Request::create('/', 'POST', [], [], [], array_filter([
                'REMOTE_ADDR' => $progressMeta['ip_address'] ?? null,
                'HTTP_USER_AGENT' => $progressMeta['user_agent'] ?? null,
            ], fn ($value) => is_string($value) && $value !== ''));

            $this->addAuditLog($run, 'bulk_adjustment_added', $user, $auditRequest, false, [
                'employee_ids' => $employeeIds,
                'employee_count' => $done,
                'type' => $type,
                'title' => $title,
                'amount' => round($amount, 2),
            ]);

            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_COMPLETED,
                'done' => $done,
                'total' => $total,
                'percent' => 100,
                'message' => 'Completed',
                'error' => null,
            ]);
        } catch (PayrollJobCancelledException) {
            if ($done > 0) {
                $auditRequest = Request::create('/', 'POST', [], [], [], array_filter([
                    'REMOTE_ADDR' => $progressMeta['ip_address'] ?? null,
                    'HTTP_USER_AGENT' => $progressMeta['user_agent'] ?? null,
                ], fn ($value) => is_string($value) && $value !== ''));

                $this->addAuditLog($run, 'bulk_adjustment_added', $user, $auditRequest, false, [
                    'employee_ids' => array_slice($employeeIds, 0, $done),
                    'employee_count' => $done,
                    'type' => $type,
                    'title' => $title,
                    'amount' => round($amount, 2),
                    'cancelled' => true,
                ]);
            }

            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_CANCELLED,
                'done' => $done,
                'total' => $total,
                'message' => $done > 0
                    ? "Cancelled after adjusting {$done} of {$total} employees"
                    : 'Cancelled',
            ]);
        } catch (\Throwable $e) {
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_FAILED,
                'done' => $done,
                'total' => $total,
                'error' => $e->getMessage(),
                'message' => 'Failed',
            ]);

            throw $e;
        } finally {
            $this->clearRunBusy($payrollRunId);
        }
    }

    protected function createAdjustmentRecord(
        PayrollRun $run,
        int $employeeId,
        string $type,
        string $title,
        float $amount,
        ?string $remarks,
        User $actor,
    ): void {
        PayrollRunAdjustment::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employeeId,
            'title' => $title,
            'type' => $type,
            'amount' => round(max(0, $amount), 2),
            'remarks' => $remarks,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);

        $this->applyManualTotalsToItem($run->id, $employeeId);
    }

    public function deleteAdjustment(
        PayrollRun $run,
        PayrollRunAdjustment $adjustment,
        User $actor,
        Request $request,
    ): void {
        if (! $run->status->isEditable()) {
            throw ValidationException::withMessages([
                'run' => 'Finalised payroll runs cannot be edited.',
            ]);
        }

        if ((int) $adjustment->payroll_run_id !== (int) $run->id) {
            abort(404);
        }

        DB::transaction(function () use ($run, $adjustment, $actor, $request): void {
            $employeeId = (int) $adjustment->employee_id;
            $context = [
                'employee_id' => $employeeId,
                'type' => $adjustment->type,
                'title' => $adjustment->title,
                'amount' => $adjustment->amount,
            ];
            $adjustment->delete();
            $this->applyManualTotalsToItem($run->id, $employeeId);
            $this->addAuditLog($run, 'adjustment_deleted', $actor, $request, false, $context);
        });
    }

    /**
     * @return array{job_id: string, payroll_run_id: int}
     */
    public function queueExport(PayrollRun $run, User $user): array
    {
        $itemCount = PayrollRunItem::query()->where('payroll_run_id', $run->id)->count();

        $jobId = $this->payrollJobProgress->create('export', $run->id, $user->id, [
            'total' => $itemCount,
        ]);

        ExportPayrollRunJob::dispatch($jobId, $run->id);

        return [
            'job_id' => $jobId,
            'payroll_run_id' => $run->id,
        ];
    }

    public function executeExportJob(string $jobId, int $payrollRunId): void
    {
        $run = PayrollRun::query()->find($payrollRunId);

        if (! $run) {
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_FAILED,
                'error' => 'Payroll run not found.',
                'message' => 'Failed',
                'download_ready' => false,
            ]);

            return;
        }

        try {
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_RUNNING,
                'message' => 'Preparing export…',
            ]);

            $built = $this->buildExportSpreadsheet($run, function (int $done, int $total) use ($jobId): void {
                if ($this->payrollJobProgress->isCancelRequested($jobId)) {
                    throw new PayrollJobCancelledException('Payroll job cancelled.');
                }

                $this->payrollJobProgress->update($jobId, [
                    'status' => PayrollJobProgress::STATUS_RUNNING,
                    'done' => $done,
                    'total' => $total,
                    'message' => $total > 0
                        ? "Exporting {$done} of {$total} employees…"
                        : 'No employees to export…',
                ]);
            });

            if ($this->payrollJobProgress->isCancelRequested($jobId)) {
                throw new PayrollJobCancelledException('Payroll job cancelled.');
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'payroll-export-');

            if ($tempPath === false) {
                throw new \RuntimeException('Unable to create temporary export file.');
            }

            $xlsxTempPath = $tempPath.'.xlsx';
            @unlink($tempPath);

            $writer = new Xlsx($built['spreadsheet']);
            $writer->save($xlsxTempPath);

            $this->payrollJobProgress->storeFile($jobId, $xlsxTempPath, $built['filename']);
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_COMPLETED,
                'percent' => 100,
                'message' => 'Completed',
                'error' => null,
                'download_ready' => true,
            ]);
        } catch (PayrollJobCancelledException) {
            $this->payrollJobProgress->deleteArtifacts($jobId);
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_CANCELLED,
                'message' => 'Cancelled',
                'download_ready' => false,
            ]);
        } catch (\Throwable $e) {
            $this->payrollJobProgress->deleteArtifacts($jobId);
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_FAILED,
                'error' => $e->getMessage(),
                'message' => 'Failed',
                'download_ready' => false,
            ]);

            throw $e;
        }
    }

    /**
     * @return array{spreadsheet: Spreadsheet, filename: string}
     */
    protected function buildExportSpreadsheet(PayrollRun $run, ?callable $onProgress = null): array
    {
        $run->loadMissing('items');

        $items = $run->items;
        $total = $items->count();
        $componentNames = [];

        foreach ($items as $item) {
            foreach ($item->details ?? [] as $detail) {
                if (! is_array($detail)) {
                    continue;
                }

                $name = trim((string) ($detail['component'] ?? ''));
                if ($name === '' || in_array($name, $componentNames, true)) {
                    continue;
                }

                $componentNames[] = $name;
            }
        }

        $headers = [
            'Staff ID',
            'Employee',
            'National ID',
            'Department',
            'Designation',
            'Bank',
            'Account Name',
            'Account No',
            'Days Attended',
            'Hours Worked',
            ...$componentNames,
            'Manual Additions',
            'Manual Deductions',
            'Gross',
            'Deductions',
            'Net',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll');
        $sheet->fromArray($headers, null, 'A1');

        $rowNum = 2;
        $done = 0;

        foreach ($items as $item) {
            $amountsByComponent = [];
            foreach ($item->details ?? [] as $detail) {
                if (! is_array($detail)) {
                    continue;
                }

                $name = trim((string) ($detail['component'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $amountsByComponent[$name] = round((float) ($detail['amount'] ?? 0), 2);
            }

            $row = [
                $item->staff_id,
                $item->employee_name,
                $item->national_id ?? '—',
                $item->department_name ?? '—',
                $item->designation_name ?? '—',
                $item->bank_name ?? '—',
                $item->account_name ?? '—',
                $item->account_no ?? '—',
                $item->days_attended,
                $item->hours_worked,
            ];

            foreach ($componentNames as $componentName) {
                $row[] = $amountsByComponent[$componentName] ?? 0;
            }

            $row[] = round((float) $item->manual_additions, 2);
            $row[] = round((float) $item->manual_deductions, 2);
            $row[] = round((float) $item->gross, 2);
            $row[] = round((float) $item->deductions, 2);
            $row[] = round((float) $item->net, 2);

            $sheet->fromArray($row, null, 'A'.$rowNum);
            $rowNum++;
            $done++;

            if ($onProgress && ($done === $total || $done % 25 === 0)) {
                $onProgress($done, $total);
            }
        }

        if ($onProgress) {
            $onProgress($done, $total);
        }

        $lastColumnIndex = count($headers);
        for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        return [
            'spreadsheet' => $spreadsheet,
            'filename' => "payroll-run-{$run->reference_no}.xlsx",
        ];
    }

    public function exportRun(PayrollRun $run): StreamedResponse
    {
        $built = $this->buildExportSpreadsheet($run);
        $writer = new Xlsx($built['spreadsheet']);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $built['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{job_id: string, payroll_run_id: int}
     */
    protected function queueCreateDraft(
        CarbonInterface $from,
        CarbonInterface $to,
        string $label,
        string $source,
        User $user,
    ): array {
        $lockKey = sprintf('payroll-create:%s:%s', $from->toDateString(), $to->toDateString());
        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'period' => 'Another payroll create is already in progress for this period.',
            ]);
        }

        try {
            $this->ensureNoOverlap($from, $to);

            $run = PayrollRun::query()->create([
                'reference_no' => 'PR-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'period_label' => $label,
                'period_source' => $source,
                'status' => PayrollRunStatus::Draft,
                'created_by_user_id' => $user->id,
            ]);

            $jobId = $this->payrollJobProgress->create('create', $run->id, $user->id);
            $this->markRunBusy($run->id, $jobId);

            BuildPayrollRunJob::dispatch($jobId, $run->id, 'create', $user->id);

            return [
                'job_id' => $jobId,
                'payroll_run_id' => $run->id,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{job_id: string, payroll_run_id: int}
     */
    protected function queueRebuild(PayrollRun $run, User $actor, string $action): array
    {
        $this->assertRunNotBusy($run->id);

        $lock = Cache::lock("payroll-rebuild:{$run->id}", 30);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'run' => 'A payroll rebuild is already in progress for this run.',
            ]);
        }

        try {
            $this->assertRunNotBusy($run->id);

            $jobId = $this->payrollJobProgress->create($action, $run->id, $actor->id);
            $this->markRunBusy($run->id, $jobId);

            BuildPayrollRunJob::dispatch($jobId, $run->id, $action, $actor->id);

            return [
                'job_id' => $jobId,
                'payroll_run_id' => $run->id,
            ];
        } finally {
            $lock->release();
        }
    }

    public function executeQueuedJob(string $jobId, int $payrollRunId, string $action, int $userId): void
    {
        $run = PayrollRun::query()->find($payrollRunId);
        $user = User::query()->find($userId);

        if (! $run || ! $user) {
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_FAILED,
                'error' => 'Payroll run or user not found.',
                'message' => 'Failed',
            ]);
            $this->clearRunBusy($payrollRunId);

            return;
        }

        try {
            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_RUNNING,
                'message' => 'Starting…',
            ]);

            $this->rebuildItemsWithProgress($run, $jobId);

            if ($action === 'process') {
                $run->update([
                    'status' => PayrollRunStatus::Processed,
                    'processed_by_user_id' => $user->id,
                    'processed_at' => now(),
                ]);
                $this->addAuditLog($run, 'processed', $user, null);
            } elseif ($action === 'rerun') {
                $this->addAuditLog($run, 'rerun', $user, null, false, [
                    'status' => $run->status->value,
                ]);
            }

            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_COMPLETED,
                'percent' => 100,
                'message' => 'Completed',
                'error' => null,
                'payroll_run_id' => $run->id,
            ]);
            $this->clearRunBusy($run->id);
        } catch (PayrollJobCancelledException) {
            if ($action === 'create') {
                $this->deleteIncompleteRun($run);
            }

            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_CANCELLED,
                'message' => 'Cancelled',
                'payroll_run_id' => $action === 'create' ? null : $run->id,
            ]);
            $this->clearRunBusy($payrollRunId);
        } catch (\Throwable $e) {
            $clearedRun = false;

            if ($action === 'create') {
                $hasItems = PayrollRunItem::query()->where('payroll_run_id', $run->id)->exists();

                if (! $hasItems) {
                    $this->deleteIncompleteRun($run);
                    $clearedRun = true;
                }
            }

            $this->payrollJobProgress->update($jobId, [
                'status' => PayrollJobProgress::STATUS_FAILED,
                'error' => $e->getMessage(),
                'message' => 'Failed',
                'payroll_run_id' => $clearedRun ? null : $run->id,
            ]);
            $this->clearRunBusy($payrollRunId);

            throw $e;
        }
    }

    protected function createDraft(
        CarbonInterface $from,
        CarbonInterface $to,
        string $label,
        string $source,
        User $user,
    ): PayrollRun {
        $this->ensureNoOverlap($from, $to);

        /** @var PayrollRun $run */
        $run = DB::transaction(function () use ($from, $to, $label, $source, $user) {
            $run = PayrollRun::query()->create([
                'reference_no' => 'PR-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'period_label' => $label,
                'period_source' => $source,
                'status' => PayrollRunStatus::Draft,
                'created_by_user_id' => $user->id,
            ]);

            $this->rebuildItems($run);

            return $run;
        });

        return $run->fresh(['items']);
    }

    public function rebuildItemsWithProgress(PayrollRun $run, string $jobId): void
    {
        $report = $this->payrollProcessingService->buildForDateRange(
            $run->period_from,
            $run->period_to,
            $run->period_label,
            null,
            function (int $done, int $total) use ($jobId): void {
                static $lastCancelCheck = 0;

                if ($done === 0 || $done === $total || ($done - $lastCancelCheck) >= 5) {
                    $lastCancelCheck = $done;

                    if ($this->payrollJobProgress->isCancelRequested($jobId)) {
                        throw new PayrollJobCancelledException('Payroll job cancelled.');
                    }
                }

                $this->payrollJobProgress->update($jobId, [
                    'status' => PayrollJobProgress::STATUS_RUNNING,
                    'done' => $done,
                    'total' => $total,
                    'message' => $total > 0
                        ? "Processing {$done} of {$total} employees…"
                        : 'No employees to process…',
                ]);
            },
        );

        if ($this->payrollJobProgress->isCancelRequested($jobId)) {
            throw new PayrollJobCancelledException('Payroll job cancelled.');
        }

        $this->payrollJobProgress->update($jobId, [
            'message' => 'Saving payroll items…',
        ]);

        $this->replaceItemsFromRows($run, $report['rows']);
    }

    protected function rebuildItems(PayrollRun $run): void
    {
        $report = $this->payrollProcessingService->buildForDateRange(
            $run->period_from,
            $run->period_to,
            $run->period_label,
        );

        $this->replaceItemsFromRows($run, $report['rows']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function replaceItemsFromRows(PayrollRun $run, array $rows): void
    {
        $existingAdjustments = PayrollRunAdjustment::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->groupBy('employee_id');

        DB::transaction(function () use ($run, $rows, $existingAdjustments): void {
            PayrollRunItem::query()->where('payroll_run_id', $run->id)->delete();

            $now = now();
            $payload = [];

            foreach ($rows as $index => $row) {
                $employeeAdjustments = $existingAdjustments->get($row['employee_id'], collect());
                $manualAdditions = (float) $employeeAdjustments->where('type', 'addition')->sum('amount');
                $manualDeductions = (float) $employeeAdjustments->where('type', 'deduction')->sum('amount');

                $payload[] = [
                    'payroll_run_id' => $run->id,
                    'employee_id' => $row['employee_id'],
                    'department_id' => $row['department_id'],
                    'department_name' => $row['department'],
                    'designation_name' => $row['designation'],
                    'staff_id' => $row['staff_id'],
                    'employee_name' => $row['employee_name'],
                    'national_id' => $row['national_id'],
                    'bank_name' => $row['bank_name'] ?? null,
                    'account_name' => $row['account_name'] ?? null,
                    'account_no' => $row['account_no'] ?? null,
                    'days_attended' => $row['days_attended'],
                    'hours_worked' => $row['hours_worked'],
                    'base_gross' => $row['gross'],
                    'base_deductions' => $row['deductions'],
                    'base_net' => $row['net'],
                    'manual_additions' => round($manualAdditions, 2),
                    'manual_deductions' => round($manualDeductions, 2),
                    'gross' => round($row['gross'] + $manualAdditions, 2),
                    'deductions' => round($row['deductions'] + $manualDeductions, 2),
                    'net' => round(($row['gross'] + $manualAdditions) - ($row['deductions'] + $manualDeductions), 2),
                    'details' => json_encode($row['details']),
                    'attendance_summary' => json_encode([
                        'days_attended' => $row['days_attended'],
                        'hours_worked' => $row['hours_worked'],
                        'late_minutes' => $row['late_minutes'] ?? null,
                        'absent_days' => $row['absent_days'] ?? null,
                        'present_days' => $row['present_days'] ?? null,
                        'formula_variables' => $row['formula_variables'] ?? null,
                    ]),
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($payload, 200) as $chunk) {
                PayrollRunItem::query()->insert($chunk);
            }
        });
    }

    protected function deleteIncompleteRun(PayrollRun $run): void
    {
        DB::transaction(function () use ($run): void {
            PayrollRunItem::query()->where('payroll_run_id', $run->id)->delete();
            PayrollRunAdjustment::query()->where('payroll_run_id', $run->id)->delete();
            PayrollRunAuditLog::query()->where('payroll_run_id', $run->id)->delete();
            $run->delete();
        });
    }

    protected function assertRunNotBusy(int $payrollRunId): void
    {
        $existingJobId = Cache::get($this->runBusyKey($payrollRunId));

        if (! is_string($existingJobId) || $existingJobId === '') {
            return;
        }

        $progress = $this->payrollJobProgress->get($existingJobId);

        if ($progress && in_array($progress['status'] ?? null, [
            PayrollJobProgress::STATUS_QUEUED,
            PayrollJobProgress::STATUS_RUNNING,
        ], true)) {
            throw ValidationException::withMessages([
                'run' => 'A payroll job is already in progress for this run.',
            ]);
        }
    }

    protected function markRunBusy(int $payrollRunId, string $jobId): void
    {
        Cache::put($this->runBusyKey($payrollRunId), $jobId, 60 * 60 * 6);
    }

    protected function clearRunBusy(int $payrollRunId): void
    {
        Cache::forget($this->runBusyKey($payrollRunId));
    }

    protected function runBusyKey(int $payrollRunId): string
    {
        return "payroll-run-busy:{$payrollRunId}";
    }

    protected function ensureNoOverlap(CarbonInterface $from, CarbonInterface $to): void
    {
        $exists = PayrollRun::query()
            ->whereDate('period_from', '<=', $to->toDateString())
            ->whereDate('period_to', '>=', $from->toDateString())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'period' => 'Payroll already exists for all or part of this selected date range.',
            ]);
        }
    }

    protected function requireRunItem(PayrollRun $run, int $employeeId): PayrollRunItem
    {
        /** @var PayrollRunItem|null $item */
        $item = PayrollRunItem::query()
            ->where('payroll_run_id', $run->id)
            ->where('employee_id', $employeeId)
            ->first();

        if (! $item) {
            abort(404);
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRunSummaryArray(PayrollRun $run): array
    {
        return [
            'id' => $run->id,
            'reference_no' => $run->reference_no,
            'period_label' => $run->period_label,
            'period_from' => $run->period_from?->toDateString(),
            'period_to' => $run->period_to?->toDateString(),
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function toEmployeeSummaryArray(PayrollRunItem $item): array
    {
        return [
            'employee_id' => $item->employee_id,
            'staff_id' => $item->staff_id,
            'employee_name' => $item->employee_name,
            'national_id' => $item->national_id,
            'department' => $item->department_name,
            'designation' => $item->designation_name,
            'days_attended' => $item->days_attended,
            'hours_worked' => $item->hours_worked,
            'base_gross' => $item->base_gross,
            'base_deductions' => $item->base_deductions,
            'base_net' => $item->base_net,
            'manual_additions' => $item->manual_additions,
            'manual_deductions' => $item->manual_deductions,
            'gross' => $item->gross,
            'deductions' => $item->deductions,
            'net' => $item->net,
            'details' => $item->details ?? [],
            'attendance_summary' => $item->attendance_summary ?? [],
        ];
    }

    protected function applyManualTotalsToItem(int $runId, int $employeeId): void
    {
        $item = PayrollRunItem::query()
            ->where('payroll_run_id', $runId)
            ->where('employee_id', $employeeId)
            ->first();

        if (! $item) {
            return;
        }

        $manualAdditions = (float) PayrollRunAdjustment::query()
            ->where('payroll_run_id', $runId)
            ->where('employee_id', $employeeId)
            ->where('type', 'addition')
            ->sum('amount');
        $manualDeductions = (float) PayrollRunAdjustment::query()
            ->where('payroll_run_id', $runId)
            ->where('employee_id', $employeeId)
            ->where('type', 'deduction')
            ->sum('amount');

        $gross = round($item->base_gross + $manualAdditions, 2);
        $deductions = round($item->base_deductions + $manualDeductions, 2);

        $item->update([
            'manual_additions' => round($manualAdditions, 2),
            'manual_deductions' => round($manualDeductions, 2),
            'gross' => $gross,
            'deductions' => $deductions,
            'net' => round($gross - $deductions, 2),
        ]);
    }

    protected function addAuditLog(
        PayrollRun $run,
        string $eventType,
        User $actor,
        ?Request $request = null,
        bool $passwordConfirmed = false,
        array $context = [],
    ): void {
        PayrollRunAuditLog::query()->create([
            'payroll_run_id' => $run->id,
            'event_type' => $eventType,
            'performed_by_user_id' => $actor->id,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'password_confirmed' => $passwordConfirmed,
            'context' => $context,
        ]);
    }

    /**
     * @param  array{gross?: float, deductions?: float, net?: float}|null  $totals
     * @return array<string, mixed>
     */
    protected function toRunArray(PayrollRun $run, ?array $totals = null): array
    {
        if ($totals === null) {
            $run->loadMissing('items');
            $totals = $run->items->reduce(
                fn (array $carry, PayrollRunItem $item) => [
                    'gross' => $carry['gross'] + $item->gross,
                    'deductions' => $carry['deductions'] + $item->deductions,
                    'net' => $carry['net'] + $item->net,
                ],
                ['gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0]
            );
            $totals = [
                'gross' => round($totals['gross'], 2),
                'deductions' => round($totals['deductions'], 2),
                'net' => round($totals['net'], 2),
            ];
        }

        return [
            'id' => $run->id,
            'reference_no' => $run->reference_no,
            'period_from' => $run->period_from?->toDateString(),
            'period_to' => $run->period_to?->toDateString(),
            'period_label' => $run->period_label,
            'period_source' => $run->period_source,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'created_at' => $run->created_at?->toIso8601String(),
            'processed_at' => $run->processed_at?->toIso8601String(),
            'finalised_at' => $run->finalised_at?->toIso8601String(),
            'finalised_version' => $run->finalised_version,
            'created_by' => $run->createdBy?->name,
            'processed_by' => $run->processedBy?->name,
            'finalised_by' => $run->finalisedBy?->name,
            'reopened_by' => $run->reopenedBy?->name,
            'totals' => $totals,
            'audit_logs' => $run->auditLogs
                ->sortByDesc('created_at')
                ->map(fn (PayrollRunAuditLog $log) => [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'performed_by' => $log->performedBy?->name,
                    'password_confirmed' => $log->password_confirmed,
                    'context' => $log->context,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRowArray(PayrollRunItem $item, PayrollRun $run): array
    {
        return [
            'id' => $item->id,
            'employee_id' => $item->employee_id,
            'staff_id' => $item->staff_id,
            'employee_name' => $item->employee_name,
            'national_id' => $item->national_id,
            'bank_name' => $item->bank_name ?: $item->employee?->bank_name,
            'account_name' => $item->account_name ?: $item->employee?->account_name,
            'account_no' => $item->account_no ?: $item->employee?->account_no,
            'department' => $item->department_name,
            'designation' => $item->designation_name,
            'days_attended' => $item->days_attended,
            'hours_worked' => $item->hours_worked,
            'base_gross' => $item->base_gross,
            'base_deductions' => $item->base_deductions,
            'manual_additions' => $item->manual_additions,
            'manual_deductions' => $item->manual_deductions,
            'gross' => $item->gross,
            'deductions' => $item->deductions,
            'net' => $item->net,
            'details' => $item->details ?? [],
            'can_edit' => $run->status->isEditable(),
        ];
    }
}
