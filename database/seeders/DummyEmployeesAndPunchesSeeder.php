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
 * Adds demo employees (staff_id DEMO###) and weekday punch history.
 * Does not modify or delete existing non-demo employees.
 */
class DummyEmployeesAndPunchesSeeder extends Seeder
{
    private const STAFF_PREFIX = 'DEMO';

    private const EMPLOYEE_COUNT = 40;

    /** Months of punch history ending today (inclusive of current month). */
    private const PUNCH_MONTHS = 3;

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
        $employees = $this->ensureDemoEmployees($grades);

        $this->command?->info('Seeding punches for '.count($employees).' demo employees…');

        $this->seedPunches($employees, $device);

        $this->command?->info('Demo employees and punches ready (existing employees left unchanged).');
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
        $profiles = array_slice($this->profiles(), 0, self::EMPLOYEE_COUNT);
        $employees = [];

        foreach ($profiles as $index => $profile) {
            $n = $index + 1;
            $staffId = sprintf('%s%03d', self::STAFF_PREFIX, $n);
            $nationalId = sprintf('A%07d', 9000000 + $n);

            $employee = Employee::query()->firstOrCreate(
                ['staff_id' => $staffId],
                [
                    'name' => $profile['name'],
                    'national_id' => $nationalId,
                    'email' => strtolower($staffId).'@demo.local',
                    'personal_email' => strtolower($staffId).'.personal@demo.local',
                    'mobile_number' => sprintf('7%07d', 1000000 + $n),
                    'joined_date' => now()->subMonths(self::PUNCH_MONTHS + 2)->startOfMonth()->toDateString(),
                    'gender' => $profile['gender'],
                    'employment_type' => $profile['employment_type'],
                    'duty_type' => DutyType::Normal,
                    'grade_id' => $gradeIds[$index % count($gradeIds)],
                    'bank_name' => ['BML', 'MIB', 'CBM'][$index % 3],
                    'account_name' => $profile['name'],
                    'account_no' => sprintf('77%08d', 10000000 + $n),
                    'is_active' => true,
                    'works_saturday' => $index % 4 === 0,
                    'nationality' => 'Maldivian',
                    'work_location' => 'Male\' Head Office',
                ],
            );

            $employees[] = $employee;
        }

        // Wire manager relationships among demo staff only.
        if (count($employees) >= 4) {
            $manager = $employees[0];
            foreach ([1, 2, 3, 4, 5] as $i) {
                if (! isset($employees[$i])) {
                    break;
                }

                if ((int) $employees[$i]->manager_id !== (int) $manager->id) {
                    $employees[$i]->update(['manager_id' => $manager->id]);
                }
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

        // Replace only demo punches on the demo device in this window (idempotent re-runs).
        ZktAttendanceLog::withTrashed()
            ->where('zkt_device_id', $device->id)
            ->whereIn('device_user_id', $staffIds)
            ->whereBetween('punched_at', [$start, $end])
            ->forceDelete();

        $nextUid = (int) ZktAttendanceLog::withTrashed()
            ->where('zkt_device_id', $device->id)
            ->max('device_uid');

        $rows = [];
        $now = now();

        foreach ($employees as $employeeIndex => $employee) {
            $worksSaturday = (bool) $employee->works_saturday;

            foreach (CarbonPeriod::create($start->copy(), $end->copy()) as $day) {
                /** @var Carbon $day */
                if ($day->isSunday()) {
                    continue;
                }

                if ($day->isSaturday() && ! $worksSaturday) {
                    continue;
                }

                // ~8% chance of a full absence day.
                if (mt_rand(1, 100) <= 8) {
                    continue;
                }

                $inHour = 8;
                $inMinute = mt_rand(40, 59);
                if (mt_rand(1, 100) <= 15) {
                    // Occasional late arrival.
                    $inHour = 9;
                    $inMinute = mt_rand(5, 40);
                }

                $outHour = 17;
                $outMinute = mt_rand(0, 45);
                if (mt_rand(1, 100) <= 10) {
                    // Occasional early leave.
                    $outHour = 16;
                    $outMinute = mt_rand(0, 30);
                }

                $checkIn = $day->copy()->setTime($inHour, $inMinute, mt_rand(0, 59));
                $checkOut = $day->copy()->setTime($outHour, $outMinute, mt_rand(0, 59));

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

                // ~5% missing checkout.
                if (mt_rand(1, 100) <= 5) {
                    continue;
                }

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

            // Slight variation per employee for randomness seed feel.
            unset($employeeIndex);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('zkt_attendance_logs')->insert($chunk);
        }

        $this->command?->info('Inserted '.count($rows).' demo punches from '.$start->toDateString().' to '.$end->toDateString().'.');
    }
}
