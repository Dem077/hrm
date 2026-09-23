<?php

namespace Database\Seeders;

use App\Enums\ApprovalWorkflowKind;
use App\Enums\AttendancePunchSource;
use App\Enums\DutyType;
use App\Enums\LeaveRequestStatus;
use App\Enums\OvertimeRequestStatus;
use App\Enums\ZktConnectionStatus;
use App\Enums\ZktMachineType;
use App\Models\AppSetting;
use App\Models\ApprovalTemplate;
use App\Models\AttendanceDutyPolicy;
use App\Models\AttendanceGeneralSetting;
use App\Models\DutyShiftTemplate;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Nationality;
use App\Models\OvertimeRequest;
use App\Models\PublicHoliday;
use App\Models\RemoteDoorSite;
use App\Models\SelfPunchSite;
use App\Models\User;
use App\Models\ZktAttendanceLog;
use App\Models\ZktDevice;
use App\Models\ZktLocationGroup;
use App\Services\Attendance\DutyRosterAssignmentService;
use App\Services\Leave\LeaveApprovalWorkflowService;
use App\Services\Leave\LeaveRequestService;
use App\Services\Overtime\OvertimeApprovalWorkflowService;
use App\Services\Overtime\OvertimeRequestService;
use App\Services\Payroll\PayrollRunService;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Seeds remaining demo data so a fresh database can exercise major app screens.
 * Expects DummyEmployeesAndPunchesSeeder (and ideally DummyCompanyStructureSeeder + DummyPayrollStructureSeeder) first.
 */
class DemoAppSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@demo.local';

    private const ADMIN_PASSWORD = 'password';

    private const STAFF_LOGIN_PASSWORD = 'password';

    public function run(): void
    {
        AttendanceDutyPolicy::ensureDefault();
        AttendanceGeneralSetting::current();

        $admin = $this->seedAdminUser();
        $this->seedAppSettings();
        $this->seedNationalities();
        $leaveTypes = $this->seedLeaveTypes();
        $this->seedPublicHolidays();
        $shiftTemplate = $this->seedDutyShiftTemplates();
        $this->seedDutyRosters($shiftTemplate);
        $this->seedGeofenceSites();
        $this->seedLocationGroups();
        $this->seedLeaveRequests($leaveTypes);
        $this->seedOvertimeRequests();
        $this->seedPayrollRun($admin);

        $this->command?->newLine();
        $this->command?->info('Demo app data ready.');
        $this->command?->info('Login: '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD);
        $this->command?->info('Staff logins: DEMO001–DEMO003 @demo.local / '.self::STAFF_LOGIN_PASSWORD);
    }

    protected function seedAdminUser(): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $superAdmin = Role::findOrCreate(PermissionRegistry::superAdminRole());
        if (! $user->hasRole($superAdmin)) {
            $user->assignRole($superAdmin);
        }

        $demoEmployees = Employee::query()
            ->where('staff_id', 'like', 'DEMO%')
            ->orderBy('staff_id')
            ->limit(3)
            ->get();

        if ($demoEmployees->isEmpty()) {
            $this->command?->warn('No DEMO employees found — admin login works, but leave/OT samples need DummyEmployeesAndPunchesSeeder.');

            return $user;
        }

        /** @var Employee $primary */
        $primary = $demoEmployees->first();

        if ($primary->user_id !== $user->id) {
            // Detach any other employee already linked to this admin user.
            Employee::query()->where('user_id', $user->id)->whereKeyNot($primary->id)->update(['user_id' => null]);
            $primary->update(['user_id' => $user->id]);
        }

        foreach ($demoEmployees->slice(1)->values() as $index => $employee) {
            $staffUser = User::query()->updateOrCreate(
                ['email' => strtolower($employee->staff_id).'@demo.local'],
                [
                    'name' => $employee->name,
                    'password' => Hash::make(self::STAFF_LOGIN_PASSWORD),
                    'email_verified_at' => now(),
                ],
            );

            if ($employee->user_id !== $staffUser->id) {
                Employee::query()->where('user_id', $staffUser->id)->whereKeyNot($employee->id)->update(['user_id' => null]);
                $employee->update(['user_id' => $staffUser->id]);
            }

            // DEMO002 gets Super Admin for dual admin testing; DEMO003 stays a plain employee login.
            if ($index === 0 && ! $staffUser->roles()->exists()) {
                $staffUser->assignRole($superAdmin);
            }
        }

        return $user;
    }

    protected function seedAppSettings(): void
    {
        $settings = AppSetting::current();

        $leaveFull = ApprovalTemplate::query()
            ->where('kind', ApprovalWorkflowKind::Leave)
            ->whereRaw('LOWER(name) = ?', ['full'])
            ->first();

        $overtimeFull = ApprovalTemplate::query()
            ->where('kind', ApprovalWorkflowKind::Overtime)
            ->whereRaw('LOWER(name) = ?', ['full'])
            ->first();

        $settings->update([
            'app_name' => $settings->app_name ?: config('app.name', 'HRM'),
            'tagline' => $settings->tagline ?: 'Demo Attendance',
            'leave_carry_forward_enabled' => true,
            'leave_approval_template_id' => $leaveFull?->id ?? $settings->leave_approval_template_id,
            'overtime_approval_template_id' => $overtimeFull?->id ?? $settings->overtime_approval_template_id,
        ]);
    }

    protected function seedNationalities(): void
    {
        foreach ([
            ['name' => 'Maldivian', 'sort_order' => 0],
            ['name' => 'Indian', 'sort_order' => 1],
            ['name' => 'Sri Lankan', 'sort_order' => 2],
            ['name' => 'Bangladeshi', 'sort_order' => 3],
            ['name' => 'Other', 'sort_order' => 99],
        ] as $row) {
            Nationality::query()->updateOrCreate(
                ['name' => $row['name']],
                ['sort_order' => $row['sort_order'], 'is_active' => true],
            );
        }
    }

    /**
     * @return array<string, LeaveType>
     */
    protected function seedLeaveTypes(): array
    {
        $defs = [
            'AL' => [
                'name' => 'Annual Leave',
                'description' => 'Paid annual leave entitlement.',
                'requires_document' => false,
                'annual_limit' => 30,
                'can_carry_forward' => true,
                'max_carry_forward_days' => 10,
                'sort_order' => 1,
            ],
            'SL' => [
                'name' => 'Sick Leave',
                'description' => 'Medical leave; document recommended for longer absences.',
                'requires_document' => true,
                'annual_limit' => 15,
                'can_carry_forward' => false,
                'max_carry_forward_days' => null,
                'sort_order' => 2,
            ],
            'CL' => [
                'name' => 'Casual Leave',
                'description' => 'Short notice personal leave.',
                'requires_document' => false,
                'annual_limit' => 10,
                'can_carry_forward' => false,
                'max_carry_forward_days' => null,
                'sort_order' => 3,
            ],
            'UL' => [
                'name' => 'Unpaid Leave',
                'description' => 'Leave without pay.',
                'requires_document' => false,
                'annual_limit' => null,
                'can_carry_forward' => false,
                'max_carry_forward_days' => null,
                'sort_order' => 4,
            ],
            'ML' => [
                'name' => 'Maternity Leave',
                'description' => 'Maternity leave for eligible employees.',
                'requires_document' => true,
                'annual_limit' => 60,
                'can_carry_forward' => false,
                'max_carry_forward_days' => null,
                'sort_order' => 5,
            ],
            'FL' => [
                'name' => 'Family Leave',
                'description' => 'Leave for family-related matters.',
                'requires_document' => false,
                'annual_limit' => 5,
                'can_carry_forward' => false,
                'max_carry_forward_days' => null,
                'sort_order' => 6,
            ],
            'DT' => [
                'name' => 'Duty Travel',
                'description' => 'Official duty travel days.',
                'requires_document' => false,
                'annual_limit' => null,
                'can_carry_forward' => false,
                'max_carry_forward_days' => null,
                'sort_order' => 7,
            ],
            'RL' => [
                'name' => 'Release',
                'description' => 'Release / early release days.',
                'requires_document' => false,
                'annual_limit' => null,
                'can_carry_forward' => false,
                'max_carry_forward_days' => null,
                'sort_order' => 8,
            ],
            'UML' => [
                'name' => 'Umra Leave',
                'description' => 'Umra / Umrah leave.',
                'requires_document' => true,
                'annual_limit' => null,
                'can_carry_forward' => false,
                'max_carry_forward_days' => null,
                'sort_order' => 9,
            ],
        ];

        $types = [];

        foreach ($defs as $code => $def) {
            $types[$code] = LeaveType::query()->updateOrCreate(
                ['code' => $code],
                [
                    ...$def,
                    'is_visible_to_employees' => true,
                    'is_active' => true,
                ],
            );
        }

        return $types;
    }

    protected function seedPublicHolidays(): void
    {
        $year = (int) now()->year;

        foreach ([
            ['name' => "New Year's Day", 'date' => "{$year}-01-01", 'notes' => 'Public holiday'],
            ['name' => 'Independence Day', 'date' => "{$year}-07-26", 'notes' => 'National holiday'],
            ['name' => 'Republic Day', 'date' => "{$year}-11-11", 'notes' => 'National holiday'],
            ['name' => 'Demo Company Day', 'date' => now()->addWeeks(2)->toDateString(), 'notes' => 'Seeded company holiday for attendance testing'],
        ] as $holiday) {
            PublicHoliday::query()->updateOrCreate(
                ['date' => $holiday['date']],
                ['name' => $holiday['name'], 'notes' => $holiday['notes']],
            );
        }
    }

    protected function seedDutyShiftTemplates(): DutyShiftTemplate
    {
        $templates = [
            [
                'name' => 'Morning Shift',
                'duty_start_time' => '07:00:00',
                'duty_end_time' => '15:00:00',
                'grace_minutes' => 15,
                'notes' => 'Demo morning shift',
                'sort_order' => 1,
            ],
            [
                'name' => 'Evening Shift',
                'duty_start_time' => '15:00:00',
                'duty_end_time' => '23:00:00',
                'grace_minutes' => 15,
                'notes' => 'Demo evening shift',
                'sort_order' => 2,
            ],
            [
                'name' => 'Night Shift',
                'duty_start_time' => '23:00:00',
                'duty_end_time' => '07:00:00',
                'grace_minutes' => 20,
                'notes' => 'Demo overnight shift',
                'sort_order' => 3,
            ],
        ];

        $first = null;

        foreach ($templates as $template) {
            $row = DutyShiftTemplate::query()->updateOrCreate(
                ['name' => $template['name']],
                [...$template, 'is_active' => true],
            );
            $first ??= $row;
        }

        return $first;
    }

    protected function seedDutyRosters(DutyShiftTemplate $template): void
    {
        $shiftEmployees = Employee::query()
            ->where('staff_id', 'like', 'DEMO%')
            ->orderBy('staff_id')
            ->skip(5)
            ->take(4)
            ->get();

        if ($shiftEmployees->isEmpty()) {
            return;
        }

        foreach ($shiftEmployees as $employee) {
            if ($employee->duty_type !== DutyType::Shift) {
                $employee->update(['duty_type' => DutyType::Shift]);
            }
        }

        $from = now()->startOfDay();
        $to = $from->copy()->addDays(13);

        app(DutyRosterAssignmentService::class)->bulkAssign(
            $shiftEmployees->pluck('id')->all(),
            $from,
            $to,
            $template->resolveDutyTimes(),
            'Demo roster seeded for shift staff',
            skipWeekends: true,
        );
    }

    protected function seedGeofenceSites(): void
    {
        $employees = Employee::query()
            ->where('staff_id', 'like', 'DEMO%')
            ->orderBy('staff_id')
            ->limit(8)
            ->pluck('id')
            ->all();

        $selfPunch = SelfPunchSite::query()->updateOrCreate(
            ['code' => 'DEMO_HQ'],
            [
                'name' => 'Demo HQ Self Punch',
                'description' => 'Male\' head office geofence for mobile self-punch testing.',
                'latitude' => 4.1755,
                'longitude' => 73.5093,
                'radius_meters' => 150,
                'max_accuracy_meters' => 250,
                'allowed_public_ips' => null,
                'require_public_ip' => false,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        if ($employees !== []) {
            $selfPunch->employees()->sync($employees);
        }

        $accessDevice = ZktDevice::query()->firstOrCreate(
            ['name' => 'Demo Access Door'],
            [
                'ip_address' => '10.10.10.51',
                'port' => 4370,
                'location' => 'Demo Office Lobby',
                'machine_type' => ZktMachineType::Access,
                'is_active' => true,
                'connection_status' => ZktConnectionStatus::Online,
                'notes' => 'Auto-created for remote door demo seeding.',
            ],
        );

        $remoteDoor = RemoteDoorSite::query()->updateOrCreate(
            ['code' => 'DEMO_DOOR'],
            [
                'zkt_device_id' => $accessDevice->id,
                'name' => 'Demo Lobby Door',
                'description' => 'Remote door open site linked to Demo Access Door.',
                'latitude' => 4.1755,
                'longitude' => 73.5093,
                'radius_meters' => 150,
                'max_accuracy_meters' => 250,
                'allowed_public_ips' => null,
                'require_public_ip' => false,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        if ($employees !== []) {
            $remoteDoor->employees()->sync(array_slice($employees, 0, 5));
        }
    }

    protected function seedLocationGroups(): void
    {
        $group = ZktLocationGroup::query()->updateOrCreate(
            ['code' => 'DEMO_CAMPUS'],
            [
                'name' => 'Demo Campus',
                'description' => 'Location group covering demo attendance and access devices.',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $deviceIds = ZktDevice::query()
            ->whereIn('name', ['Demo Gate', 'Demo Access Door'])
            ->pluck('id')
            ->all();

        if ($deviceIds !== []) {
            $group->devices()->sync($deviceIds);
        }

        $employeeIds = Employee::query()
            ->where('staff_id', 'like', 'DEMO%')
            ->orderBy('staff_id')
            ->limit(15)
            ->pluck('id')
            ->all();

        if ($employeeIds !== []) {
            $group->employees()->sync($employeeIds);
        }
    }

    /**
     * @param  array<string, LeaveType>  $leaveTypes
     */
    protected function seedLeaveRequests(array $leaveTypes): void
    {
        $employees = Employee::query()
            ->where('staff_id', 'like', 'DEMO%')
            ->whereNotNull('manager_id')
            ->orderBy('staff_id')
            ->limit(5)
            ->get();

        if ($employees->isEmpty()) {
            $employees = Employee::query()
                ->where('staff_id', 'like', 'DEMO%')
                ->orderBy('staff_id')
                ->skip(1)
                ->take(4)
                ->get();
        }

        if ($employees->isEmpty() || ! isset($leaveTypes['AL'])) {
            return;
        }

        if (LeaveRequest::query()->where('reason', 'like', 'Demo seeded%')->exists()) {
            return;
        }

        $leaveService = app(LeaveRequestService::class);
        $workflow = app(LeaveApprovalWorkflowService::class);
        $timezone = config('app.timezone', 'UTC');

        $samples = [
            [
                'employee' => $employees[0],
                'type' => $leaveTypes['AL'],
                'start' => now($timezone)->addDays(5)->toDateString(),
                'end' => now($timezone)->addDays(6)->toDateString(),
                'reason' => 'Demo seeded annual leave (pending)',
                'finalize' => null,
            ],
            [
                'employee' => $employees[1] ?? $employees[0],
                'type' => $leaveTypes['CL'],
                'start' => now($timezone)->addDays(10)->toDateString(),
                'end' => now($timezone)->addDays(10)->toDateString(),
                'reason' => 'Demo seeded casual leave (pending HR if no manager path)',
                'finalize' => null,
            ],
            [
                'employee' => $employees[2] ?? $employees[0],
                'type' => $leaveTypes['SL'],
                'start' => now($timezone)->subDays(14)->toDateString(),
                'end' => now($timezone)->subDays(13)->toDateString(),
                'reason' => 'Demo seeded sick leave (approved)',
                'finalize' => LeaveRequestStatus::Approved,
            ],
            [
                'employee' => $employees[3] ?? $employees[0],
                'type' => $leaveTypes['AL'],
                'start' => now($timezone)->subDays(21)->toDateString(),
                'end' => now($timezone)->subDays(20)->toDateString(),
                'reason' => 'Demo seeded annual leave (rejected)',
                'finalize' => LeaveRequestStatus::Rejected,
            ],
        ];

        foreach ($samples as $sample) {
            /** @var Employee $employee */
            $employee = $sample['employee'];
            $start = Carbon::parse($sample['start'], $timezone)->startOfDay();
            $end = Carbon::parse($sample['end'], $timezone)->startOfDay();

            $leaveRequest = LeaveRequest::query()->create([
                'record_number' => $leaveService->generateRecordNumber(),
                'employee_id' => $employee->id,
                'leave_type_id' => $sample['type']->id,
                'start_date' => $start,
                'end_date' => $end,
                'days_count' => $leaveService->calculateDaysCount($start, $end),
                'reason' => $sample['reason'],
                'status' => LeaveRequestStatus::Pending,
                'approver_employee_id' => null,
            ]);

            $firstApprover = $workflow->initializeSteps($leaveRequest, $employee);

            if ($firstApprover) {
                $leaveRequest->update([
                    'status' => LeaveRequestStatus::Pending,
                    'approver_employee_id' => $firstApprover->id,
                ]);
            } else {
                $leaveRequest->update([
                    'status' => LeaveRequestStatus::PendingHr,
                    'approver_employee_id' => null,
                ]);
            }

            if ($sample['finalize'] instanceof LeaveRequestStatus) {
                $leaveRequest->update([
                    'status' => $sample['finalize'],
                    'reviewed_at' => now(),
                    'review_notes' => 'Demo seed final status',
                ]);
            }
        }
    }

    protected function seedOvertimeRequests(): void
    {
        if (OvertimeRequest::query()->where('reason', 'like', 'Demo seeded%')->exists()) {
            return;
        }

        $employee = Employee::query()
            ->where('staff_id', 'like', 'DEMO%')
            ->whereNotNull('manager_id')
            ->orderBy('staff_id')
            ->first()
            ?? Employee::query()->where('staff_id', 'like', 'DEMO%')->orderBy('staff_id')->skip(1)->first();

        if (! $employee) {
            return;
        }

        $this->ensureLateCheckoutPunches($employee);

        $overtimeService = app(OvertimeRequestService::class);
        $workflow = app(OvertimeApprovalWorkflowService::class);
        $eligibleDays = array_slice($overtimeService->eligibleOvertimeDays($employee), 0, 3);

        if ($eligibleDays === []) {
            // Fallback sample so the OT list is not empty even if duty policy / punches disagree.
            $date = now()->subWeekdays(3)->toDateString();
            $overtimeRequest = OvertimeRequest::query()->create([
                'record_number' => $overtimeService->generateRecordNumber(),
                'employee_id' => $employee->id,
                'overtime_date' => $date,
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'hours' => 2,
                'reason' => 'Demo seeded overtime (manual fallback)',
                'status' => OvertimeRequestStatus::Pending,
                'approver_employee_id' => null,
            ]);

            $firstApprover = $workflow->initializeSteps($overtimeRequest, $employee);
            $overtimeRequest->update([
                'status' => $firstApprover ? OvertimeRequestStatus::Pending : OvertimeRequestStatus::PendingHr,
                'approver_employee_id' => $firstApprover?->id,
            ]);

            return;
        }

        foreach ($eligibleDays as $index => $day) {
            $hours = min(2.0, (float) $day['available_hours']);
            $overtimeRequest = OvertimeRequest::query()->create([
                'record_number' => $overtimeService->generateRecordNumber(),
                'employee_id' => $employee->id,
                'overtime_date' => $day['overtime_date'],
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time'],
                'hours' => $hours,
                'reason' => 'Demo seeded overtime claim #'.($index + 1),
                'status' => OvertimeRequestStatus::Pending,
                'approver_employee_id' => null,
            ]);

            $firstApprover = $workflow->initializeSteps($overtimeRequest, $employee);
            $overtimeRequest->update([
                'status' => $firstApprover ? OvertimeRequestStatus::Pending : OvertimeRequestStatus::PendingHr,
                'approver_employee_id' => $firstApprover?->id,
            ]);
        }
    }

    protected function ensureLateCheckoutPunches(Employee $employee): void
    {
        $device = ZktDevice::query()->where('name', 'Demo Gate')->first();

        if (! $device) {
            return;
        }

        $nextUid = (int) ZktAttendanceLog::withTrashed()
            ->where('zkt_device_id', $device->id)
            ->max('device_uid');

        $now = now();

        for ($i = 2; $i <= 6; $i++) {
            $day = now()->subWeekdays($i)->startOfDay();

            if ($day->isSunday()) {
                continue;
            }

            $exists = ZktAttendanceLog::query()
                ->where('zkt_device_id', $device->id)
                ->where('device_user_id', $employee->staff_id)
                ->whereDate('punched_at', $day->toDateString())
                ->where('punch_state', 1)
                ->whereTime('punched_at', '>=', '19:00:00')
                ->exists();

            if ($exists) {
                continue;
            }

            // Ensure there is at least an in punch that day.
            $hasIn = ZktAttendanceLog::query()
                ->where('zkt_device_id', $device->id)
                ->where('device_user_id', $employee->staff_id)
                ->whereDate('punched_at', $day->toDateString())
                ->where('punch_state', 0)
                ->exists();

            if (! $hasIn) {
                $nextUid++;
                ZktAttendanceLog::query()->create([
                    'zkt_device_id' => $device->id,
                    'device_uid' => $nextUid,
                    'device_user_id' => $employee->staff_id,
                    'punch_state' => 0,
                    'punch_type' => null,
                    'punched_at' => $day->copy()->setTime(8, 50, 0),
                    'source' => AttendancePunchSource::Device,
                ]);
            }

            // Replace any early checkout that day so OT eligibility can see a late exit.
            ZktAttendanceLog::query()
                ->where('zkt_device_id', $device->id)
                ->where('device_user_id', $employee->staff_id)
                ->whereDate('punched_at', $day->toDateString())
                ->where('punch_state', 1)
                ->delete();

            $nextUid++;
            ZktAttendanceLog::query()->create([
                'zkt_device_id' => $device->id,
                'device_uid' => $nextUid,
                'device_user_id' => $employee->staff_id,
                'punch_state' => 1,
                'punch_type' => null,
                'punched_at' => $day->copy()->setTime(20, 15, 0),
                'source' => AttendancePunchSource::Device,
            ]);
        }

        unset($now);
    }

    protected function seedPayrollRun(User $admin): void
    {
        $service = app(PayrollRunService::class);
        $request = Request::create('/payroll', 'POST');

        try {
            $draft = $service->createDraftFromGlobalPeriod($admin);
        } catch (\Throwable) {
            // Current period already has a run — seed last month as a custom period instead.
            try {
                $from = now()->subMonth()->startOfMonth();
                $to = now()->subMonth()->endOfMonth()->startOfDay();
                $draft = $service->createDraftFromCustomPeriod($from, $to, $admin);
            } catch (\Throwable $e) {
                $this->command?->warn('Skipped payroll run seed: '.$e->getMessage());

                return;
            }
        }

        try {
            $service->process($draft, $admin, $request);
            $this->command?->info('Seeded a processed payroll run ('.$draft->period_label.').');
        } catch (\Throwable $e) {
            $this->command?->warn('Created payroll draft but process failed: '.$e->getMessage());
        }
    }
}
