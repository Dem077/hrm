<?php

namespace App\Services\Reports;

use App\Enums\AttendanceDayStatus;
use App\Models\Employee;
use App\Services\Attendance\AttendanceSheetService;
use Carbon\CarbonInterface;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportService
{
    /**
     * Leave-type buckets keyed by report column.
     * Matching is case-insensitive against leave type name or common codes.
     *
     * @var array<string, list<string>>
     */
    public const LEAVE_BUCKETS = [
        'annual_leave' => ['annual leave', 'al', 'annual'],
        'family_leave' => ['family leave', 'fl', 'family'],
        'sick_leave' => ['sick leave', 'sl', 'sick'],
        'duty_travel' => ['duty travel', 'duty trav', 'dt'],
        'release' => ['release'],
        'umra_leave' => ['umra leave', 'umrah leave', 'umra', 'umrah'],
        'maternity_leave' => ['maternity leave', 'ml', 'maternity'],
    ];

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'emp_no',
            'name',
            'nid',
            'department',
            'late min',
            'Normal',
            'Annual Leave',
            'Family Leave',
            'Holiday',
            'Sick Leave',
            'Absent',
            'Late',
            'Duty Travel',
            'Release',
            'Umra Leave',
            'Maternity Leave',
            'N/A',
        ];
    }

    public function __construct(
        protected AttendanceSheetService $sheetService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $departmentId = null,
    ): array {
        $timezone = config('app.timezone', 'UTC');
        $from = $from->copy()->timezone($timezone)->startOfDay();
        $to = $to->copy()->timezone($timezone)->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $dayRows = $this->collectDayRows($from, $to, $departmentId);
        $nationalIds = Employee::query()
            ->whereIn('id', collect($dayRows)->pluck('employee_id')->unique()->filter()->all())
            ->pluck('national_id', 'id');

        $grouped = [];

        foreach ($dayRows as $row) {
            $employeeId = (int) $row['employee_id'];

            if (! isset($grouped[$employeeId])) {
                $grouped[$employeeId] = $this->emptyEmployeeRow(
                    staffId: (string) ($row['staff_id'] ?? ''),
                    name: (string) ($row['employee_name'] ?? ''),
                    nid: $nationalIds[$employeeId] ?? null,
                    department: $row['department'] ?? null,
                );
            }

            $this->accumulateDay($grouped[$employeeId], $row);
        }

        return collect($grouped)
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values()
            ->all();
    }

    public function download(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $departmentId = null,
    ): StreamedResponse {
        $rows = $this->rows($from, $to, $departmentId);
        $csv = Writer::createFromFileObject(new SplTempFileObject);
        $csv->insertOne(self::headers());

        foreach ($rows as $row) {
            $csv->insertOne([
                $row['emp_no'],
                $row['name'],
                $row['nid'] ?? '',
                $row['department'] ?? '',
                $row['late_min'],
                $row['normal'],
                $row['annual_leave'],
                $row['family_leave'],
                $row['holiday'],
                $row['sick_leave'],
                $row['absent'],
                $row['late'],
                $row['duty_travel'],
                $row['release'],
                $row['umra_leave'],
                $row['maternity_leave'],
                $row['n_a'],
            ]);
        }

        $filename = 'attendance-report-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(
            fn () => print ($csv->toString()),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectDayRows(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $departmentId,
    ): array {
        $rows = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $chunkEnd = $cursor->copy()->addDays(AttendanceSheetService::MAX_DAYS);
            if ($chunkEnd->gt($to)) {
                $chunkEnd = $to->copy();
            }

            $chunk = $this->sheetService->build($cursor, $chunkEnd, $departmentId, null);
            array_push($rows, ...$chunk['rows']);

            $cursor = $chunkEnd->copy()->addDay();
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyEmployeeRow(
        string $staffId,
        string $name,
        ?string $nid,
        ?string $department,
    ): array {
        return [
            'emp_no' => $staffId,
            'name' => $name,
            'nid' => $nid,
            'department' => $department,
            'late_min' => 0,
            'normal' => 0,
            'annual_leave' => 0,
            'family_leave' => 0,
            'holiday' => 0,
            'sick_leave' => 0,
            'absent' => 0,
            'late' => 0,
            'duty_travel' => 0,
            'release' => 0,
            'umra_leave' => 0,
            'maternity_leave' => 0,
            'n_a' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @param  array<string, mixed>  $row
     */
    protected function accumulateDay(array &$aggregate, array $row): void
    {
        $status = AttendanceDayStatus::tryFrom((string) ($row['status'] ?? ''));
        $aggregate['late_min'] += (int) ($row['late_minutes'] ?? 0);

        if ($status === null) {
            $aggregate['n_a']++;

            return;
        }

        match ($status) {
            AttendanceDayStatus::Present, AttendanceDayStatus::Incomplete => $aggregate['normal']++,
            AttendanceDayStatus::Late => $this->accumulateLate($aggregate),
            AttendanceDayStatus::Absent => $aggregate['absent']++,
            AttendanceDayStatus::Holiday => $aggregate['holiday']++,
            AttendanceDayStatus::Leave => $this->accumulateLeave($aggregate, (string) ($row['leave_type_name'] ?? '')),
        };
    }

    /**
     * @param  array<string, mixed>  $aggregate
     */
    protected function accumulateLate(array &$aggregate): void
    {
        $aggregate['late']++;
        // Late days are still attended days for the "Normal" total in many payroll sheets;
        // this report keeps them separate: Late = days late, Normal = on-time/incomplete only.
    }

    /**
     * @param  array<string, mixed>  $aggregate
     */
    protected function accumulateLeave(array &$aggregate, string $leaveTypeName): void
    {
        $bucket = $this->resolveLeaveBucket($leaveTypeName);

        if ($bucket === null) {
            $aggregate['n_a']++;

            return;
        }

        $aggregate[$bucket]++;
    }

    protected function resolveLeaveBucket(string $leaveTypeName): ?string
    {
        $normalized = mb_strtolower(trim($leaveTypeName));

        if ($normalized === '') {
            return null;
        }

        foreach (self::LEAVE_BUCKETS as $bucket => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalized === $alias) {
                    return $bucket;
                }
            }
        }

        return null;
    }
}
