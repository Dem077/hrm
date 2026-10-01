<?php

namespace Database\Seeders;

use App\Enums\AttendancePunchSource;
use App\Enums\DutyType;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\ZktConnectionStatus;
use App\Enums\ZktMachineType;
use App\Models\Employee;
use App\Models\StructureGrade;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ensures demo employees (staff_id DEMO####) exist, then seeds weekday punch history
 * for those demo employees so payroll / attendance queues can be load-tested.
 * Only replaces punches on the Demo Gate device for the seeded window.
 */
class DummyEmployeesAndPunchesSeeder extends Seeder
{
    private const STAFF_PREFIX = 'DEMO';

    private const EMPLOYEE_COUNT = 2000;

    /** Months of punch history ending yesterday (inclusive of current month). */
    private const PUNCH_MONTHS = 1;

    public function run(): void
    {
        $grades = StructureGrade::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($grades === []) {
            $this->command?->warn('No active structure grades found. Run CompanyStructureSeeder first.');

            return;
        }

        $device = $this->demoDevice();
        $demoEmployees = $this->ensureDemoEmployees($grades);

        $this->command?->info('Seeding punches for '.count($demoEmployees).' demo employees…');

        $this->seedPunches($demoEmployees, $device);

        $this->command?->info('Ready: '.count($demoEmployees).' DEMO employees with punch history for queue testing.');
    }

    protected function demoDevice(): ZktDevice
    {
        return ZktDevice::query()->firstOrCreate(
            ['name' => 'Demo Gate'],
            [
                'ip_address' => '10.10.10.50',
                'port' => 4370,
                'location' => 'Demo Office',
                'machine_type' => ZktMachineType::Attendance,
                'is_active' => true,
                'connection_status' => ZktConnectionStatus::Online,
                'notes' => 'Auto-created for demo punch seeding.',
            ],
        );
    }

    /**
     * @return list<array{name: string, gender: Gender, employment_type: EmploymentType}>
     */
    protected function profiles(): array
    {
        return [
            ['name' => 'Aisha Mohamed', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Hassan Ali', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Fathimath Risha', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Contract],
            ['name' => 'Ibrahim Zahir', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Mariyam Saeed', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Probation],
            ['name' => 'Ahmed Nizar', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Aminath Shuba', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Mohamed Rasheed', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Contract],
            ['name' => 'Hudha Yoosuf', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Ismail Shareef', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Temporary],
            ['name' => 'Sanaa Abdulla', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Yoosuf Rilwan', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Intern],
            ['name' => 'Hawwa Nishana', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Ali Waheed', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Zainab Manik', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Contract],
            ['name' => 'Hussain Faisal', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Mariyam Nashwa', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Ahmed Shiyan', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Probation],
            ['name' => 'Fathmath Latheefa', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Mohamed Imran', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Aishath Reesha', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Contract],
            ['name' => 'Ibrahim Naif', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Aminath Laila', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Hassan Thoha', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Temporary],
            ['name' => 'Mariyam Shimla', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Ahmed Mauroof', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Fathimath Zuhudha', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Contract],
            ['name' => 'Mohamed Habeeb', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Aishath Solih', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Ibrahim Athif', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Intern],
            ['name' => 'Hawwa Samha', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Ali Shareef', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Mariyam Lubna', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Probation],
            ['name' => 'Hussain Rifath', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Aminath Reesha', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Contract],
            ['name' => 'Ahmed Zayan', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Fathmath Nazima', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Mohamed Sinan', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Temporary],
            ['name' => 'Aishath Inasha', 'gender' => Gender::Female, 'employment_type' => EmploymentType::Permanent],
            ['name' => 'Yoosuf Naushad', 'gender' => Gender::Male, 'employment_type' => EmploymentType::Permanent],
        ];
    }

    /**
     * @param  list<int>  $gradeIds
     * @return list<Employee>
     */
    protected function ensureDemoEmployees(array $gradeIds): array
    {
        $profiles = $this->profiles();
        $profileCount = count($profiles);
        $joinedDate = now()->subMonths(self::PUNCH_MONTHS + 2)->startOfMonth()->toDateString();
        $now = now();
        $banks = ['BML', 'MIB', 'CBM'];

        $existingStaffIds = Employee::query()
            ->where('staff_id', 'like', self::STAFF_PREFIX.'%')
            ->pluck('staff_id');

        $existingNumbers = $existingStaffIds
            ->map(function (string $staffId): int {
                return (int) preg_replace('/\D+/', '', substr($staffId, strlen(self::STAFF_PREFIX)));
            })
            ->filter(fn (int $n) => $n > 0)
            ->unique()
            ->values();

        $existingCount = $existingNumbers->count();
        $needed = max(0, self::EMPLOYEE_COUNT - $existingCount);
        $nextNumber = ($existingNumbers->max() ?: 0) + 1;

        $this->command?->info("Demo employees already present: {$existingCount}. Creating {$needed} more…");

        $toInsert = [];

        for ($i = 0; $i < $needed; $i++) {
            $n = $nextNumber + $i;
            $staffId = sprintf('%s%04d', self::STAFF_PREFIX, $n);
            $profile = $profiles[($n - 1) % $profileCount];
            $batch = (int) ceil($n / $profileCount);
            $name = $batch === 1
                ? $profile['name']
                : $profile['name'].' '.$batch;

            $toInsert[] = [
                'staff_id' => $staffId,
                'name' => $name,
                'national_id' => sprintf('A%07d', 9000000 + $n),
                'email' => strtolower($staffId).'@demo.local',
                'personal_email' => strtolower($staffId).'.personal@demo.local',
                'mobile_number' => sprintf('7%07d', 1000000 + $n),
                'joined_date' => $joinedDate,
                'gender' => $profile['gender']->value,
                'employment_type' => $profile['employment_type']->value,
                'duty_type' => DutyType::Normal->value,
                'grade_id' => $gradeIds[($n - 1) % count($gradeIds)],
                'bank_name' => $banks[($n - 1) % 3],
                'account_name' => $name,
                'account_no' => sprintf('77%08d', 10000000 + $n),
                'is_active' => true,
                'works_saturday' => $n % 4 === 0,
                'nationality' => 'Maldivian',
                'work_location' => 'Male\' Head Office',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($toInsert, 250) as $chunk) {
            DB::table('employees')->insert($chunk);
        }

        if ($toInsert !== []) {
            $this->command?->info('Inserted '.count($toInsert).' new demo employees.');
        }

        $employees = Employee::query()
            ->where('staff_id', 'like', self::STAFF_PREFIX.'%')
            ->where('is_active', true)
            ->orderBy('staff_id')
            ->limit(self::EMPLOYEE_COUNT)
            ->get()
            ->all();

        if (count($employees) >= 4) {
            $managerId = $employees[0]->id;
            $followerIds = collect(array_slice($employees, 1, 5))
                ->filter(fn (Employee $follower) => (int) $follower->manager_id !== (int) $managerId)
                ->pluck('id')
                ->all();

            if ($followerIds !== []) {
                Employee::query()->whereIn('id', $followerIds)->update(['manager_id' => $managerId]);
            }
        }

        return $employees;
    }

    /**
     * @param  list<Employee>  $employees
     */
    protected function seedPunches(array $employees, ZktDevice $device): void
    {
        $start = now()->subMonths(self::PUNCH_MONTHS - 1)->startOfMonth()->startOfDay();
        $end = now()->subDay()->endOfDay();

        if ($end->lt($start)) {
            return;
        }

        $staffIds = array_map(fn (Employee $e) => $e->staff_id, $employees);

        $this->command?->info('Clearing previous demo punches…');

        foreach (array_chunk($staffIds, 500) as $staffChunk) {
            ZktAttendanceLog::withTrashed()
                ->where('zkt_device_id', $device->id)
                ->whereIn('device_user_id', $staffChunk)
                ->whereBetween('punched_at', [$start, $end])
                ->forceDelete();
        }

        $nextUid = (int) ZktAttendanceLog::withTrashed()
            ->where('zkt_device_id', $device->id)
            ->max('device_uid');

        $rows = [];
        $now = now();
        $inserted = 0;
        $workdays = [];

        foreach (CarbonPeriod::create($start->copy(), $end->copy()) as $day) {
            /** @var Carbon $day */
            if ($day->isSunday()) {
                continue;
            }

            $workdays[] = $day->copy();
        }

        $total = count($employees);
        $progressEvery = max(1, (int) floor($total / 10));

        foreach ($employees as $employeeIndex => $employee) {
            $worksSaturday = (bool) $employee->works_saturday;

            foreach ($workdays as $day) {
                if ($day->isSaturday() && ! $worksSaturday) {
                    continue;
                }

                // Deterministic-ish absence (~8%) without mt_rand storms per day.
                $seed = crc32($employee->staff_id.'|'.$day->toDateString());
                if (($seed % 100) < 8) {
                    continue;
                }

                $late = ($seed % 100) >= 85;
                $earlyLeave = (($seed >> 8) % 100) < 10;
                $missingCheckout = (($seed >> 16) % 100) < 5;

                $inHour = $late ? 9 : 8;
                $inMinute = $late ? (5 + ($seed % 36)) : (40 + ($seed % 20));
                $outHour = $earlyLeave ? 16 : 17;
                $outMinute = $earlyLeave ? ($seed % 31) : ($seed % 46);

                $checkIn = $day->copy()->setTime($inHour, $inMinute, $seed % 60);
                $checkOut = $day->copy()->setTime($outHour, $outMinute, ($seed >> 4) % 60);

                if ($checkOut->lte($checkIn)) {
                    $checkOut = $checkIn->copy()->addHours(8);
                }

                $nextUid++;
                $rows[] = [
                    'zkt_device_id' => $device->id,
                    'device_uid' => $nextUid,
                    'device_user_id' => $employee->staff_id,
                    'punch_state' => 0,
                    'punch_type' => null,
                    'punched_at' => $checkIn,
                    'source' => AttendancePunchSource::Device->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (! $missingCheckout) {
                    $nextUid++;
                    $rows[] = [
                        'zkt_device_id' => $device->id,
                        'device_uid' => $nextUid,
                        'device_user_id' => $employee->staff_id,
                        'punch_state' => 1,
                        'punch_type' => null,
                        'punched_at' => $checkOut,
                        'source' => AttendancePunchSource::Device->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($rows) >= 1000) {
                    DB::table('zkt_attendance_logs')->insert($rows);
                    $inserted += count($rows);
                    $rows = [];
                }
            }

            if (($employeeIndex + 1) % $progressEvery === 0 || ($employeeIndex + 1) === $total) {
                $this->command?->info('Punch progress: '.($employeeIndex + 1).'/'.$total);
            }
        }

        if ($rows !== []) {
            DB::table('zkt_attendance_logs')->insert($rows);
            $inserted += count($rows);
        }

        $this->command?->info("Inserted {$inserted} demo punches from {$start->toDateString()} to {$end->toDateString()}.");
    }
}
