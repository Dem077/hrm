<?php

namespace App\Services\Payroll;

use App\Enums\PayrollRunStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunService
{
    public function __construct(
        private readonly PayrollPeriodService $payrollPeriodService,
        private readonly PayrollProcessingService $payrollProcessingService,
        private readonly AttendanceSheetService $attendanceSheetService,
    ) {}

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
     * @return array{run: array<string, mixed>, rows: list<array<string, mixed>>, can_edit: bool}
     */
    public function detailPayload(PayrollRun $run, ?string $query = null): array
    {
        $run->loadMissing([
            'items' => fn ($builder) => $builder
                ->with('employee:id,bank_name,account_name,account_no')
                ->orderBy('bank_name')
                ->orderBy('employee_name'),
            'createdBy:id,name',
            'processedBy:id,name',
            'finalisedBy:id,name',
            'reopenedBy:id,name',
            'auditLogs.performedBy:id,name',
        ]);

        $rows = $run->items
            ->map(fn (PayrollRunItem $item) => $this->toRowArray($item, $run))
            ->values();

        if (filled($query)) {
            $needle = mb_strtolower((string) $query);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower((string) $row['employee_name']), $needle)
                || str_contains(mb_strtolower((string) ($row['staff_id'] ?? '')), $needle)
                || str_contains(mb_strtolower((string) ($row['national_id'] ?? '')), $needle)
                || str_contains(mb_strtolower((string) ($row['bank_name'] ?? '')), $needle)
                || str_contains(mb_strtolower((string) ($row['account_no'] ?? '')), $needle))
                ->values();
        }

        $bankTotals = $rows
            ->groupBy(fn (array $row) => filled($row['bank_name']) ? (string) $row['bank_name'] : 'No bank')
            ->map(fn ($bankRows, $bank) => [
                'bank' => $bank,
                'employee_count' => $bankRows->count(),
                'gross' => round($bankRows->sum('gross'), 2),
                'deductions' => round($bankRows->sum('deductions'), 2),
                'net' => round($bankRows->sum('net'), 2),
            ])
            ->sortBy('bank')
            ->values()
            ->all();

        return [
            'run' => $this->toRunArray($run),
            'rows' => $rows->all(),
            'bank_totals' => $bankTotals,
            'can_edit' => $run->status->isEditable(),
        ];
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
            $this->addAuditLog($run, 'adjustment_added', $actor, $request, false, [
                'employee_id' => $employeeId,
                'type' => $type,
                'title' => $title,
                'amount' => round($amount, 2),
            ]);
        });
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

    public function exportRun(PayrollRun $run): StreamedResponse
    {
        $run->loadMissing('items');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll');
        $sheet->fromArray([
            'Staff ID',
            'Employee',
            'Department',
            'Designation',
            'Days Attended',
            'Hours Worked',
            'Gross',
            'Deductions',
            'Net',
        ], null, 'A1');

        $rowNum = 2;
        foreach ($run->items as $item) {
            $sheet->fromArray([
                $item->staff_id,
                $item->employee_name,
                $item->department_name ?? '—',
                $item->designation_name ?? '—',
                $item->days_attended,
                $item->hours_worked,
                $item->gross,
                $item->deductions,
                $item->net,
            ], null, 'A'.$rowNum);
            $rowNum++;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "payroll-run-{$run->reference_no}.xlsx";

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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

    protected function rebuildItems(PayrollRun $run): void
    {
        $report = $this->payrollProcessingService->buildForDateRange(
            $run->period_from,
            $run->period_to,
            $run->period_label,
        );

        $existingAdjustments = PayrollRunAdjustment::query()
            ->where('payroll_run_id', $run->id)
            ->get()
            ->groupBy('employee_id');

        PayrollRunItem::query()->where('payroll_run_id', $run->id)->delete();

        foreach ($report['rows'] as $index => $row) {
            $employeeAdjustments = $existingAdjustments->get($row['employee_id'], collect());
            $manualAdditions = (float) $employeeAdjustments->where('type', 'addition')->sum('amount');
            $manualDeductions = (float) $employeeAdjustments->where('type', 'deduction')->sum('amount');

            PayrollRunItem::query()->create([
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
                'details' => $row['details'],
                'attendance_summary' => [
                    'days_attended' => $row['days_attended'],
                    'hours_worked' => $row['hours_worked'],
                ],
                'sort_order' => $index,
            ]);
        }
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
        Request $request,
        bool $passwordConfirmed = false,
        array $context = [],
    ): void {
        PayrollRunAuditLog::query()->create([
            'payroll_run_id' => $run->id,
            'event_type' => $eventType,
            'performed_by_user_id' => $actor->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'password_confirmed' => $passwordConfirmed,
            'context' => $context,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRunArray(PayrollRun $run): array
    {
        $totals = $run->items->reduce(
            fn (array $carry, PayrollRunItem $item) => [
                'gross' => $carry['gross'] + $item->gross,
                'deductions' => $carry['deductions'] + $item->deductions,
                'net' => $carry['net'] + $item->net,
            ],
            ['gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0]
        );

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
            'totals' => [
                'gross' => round($totals['gross'], 2),
                'deductions' => round($totals['deductions'], 2),
                'net' => round($totals['net'], 2),
            ],
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
