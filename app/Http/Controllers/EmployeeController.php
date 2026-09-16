<?php

namespace App\Http\Controllers;

use App\Enums\DutyType;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\BloodGroup;
use App\Enums\MaritalStatus;
use App\Enums\ZktDevicePrivilege;
use App\Http\Requests\ImportEmployeeRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\Nationality;
use App\Models\User;
use App\Models\ZktLocationGroup;
use App\Services\CompanyStructure\CompanyStructureService;
use App\Services\Employee\EmployeeCsvService;
use App\Services\Zkt\ZktDeviceUserSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EmployeeController extends Controller
{
    private const IMPORT_SESSION_KEY = 'employee_import';

    public function __construct(
        protected EmployeeCsvService $employeeCsvService,
    ) {}

    public function index(Request $request): Response
    {
        $pending = $request->session()->get(self::IMPORT_SESSION_KEY);

        return Inertia::render('Employees/Index', [
            'employees' => Employee::query()
                ->with(['grade.level.group', 'grade.level.node.group', 'manager:id,name,staff_id', 'user:id,name,email'])
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $employee) => $this->formatEmployee($employee)),
            'importPreview' => is_array($pending) ? ($pending['preview'] ?? null) : null,
            'importFileName' => is_array($pending) ? ($pending['original_name'] ?? null) : null,
        ]);
    }

    public function downloadSample(): StreamedResponse
    {
        return $this->employeeCsvService->downloadSample();
    }

    public function previewImport(ImportEmployeeRequest $request): RedirectResponse
    {
        $this->clearPendingImport($request);

        $file = $request->file('file');
        $preview = $this->employeeCsvService->preview($file);
        $path = $file->store('employee-imports');

        $request->session()->put(self::IMPORT_SESSION_KEY, [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'preview' => $preview,
        ]);

        return back();
    }

    public function import(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::IMPORT_SESSION_KEY);

        if (! is_array($pending) || empty($pending['path']) || ! Storage::disk('local')->exists($pending['path'])) {
            return back()->with('error', 'No import file ready to confirm. Upload a CSV again.');
        }

        $absolutePath = Storage::disk('local')->path($pending['path']);
        $uploaded = new UploadedFile(
            $absolutePath,
            $pending['original_name'] ?? 'employees.csv',
            'text/csv',
            null,
            true,
        );

        $stats = $this->employeeCsvService->import($uploaded);
        $this->clearPendingImport($request);

        return redirect()
            ->route('employees.index')
            ->with(
                'success',
                sprintf(
                    'Imported %d employee(s) from CSV. Temporary login passwords were generated for each account.',
                    $stats['created'],
                ),
            );
    }

    public function cancelImport(Request $request): RedirectResponse
    {
        $this->clearPendingImport($request);

        return back();
    }

    protected function clearPendingImport(Request $request): void
    {
        $pending = $request->session()->get(self::IMPORT_SESSION_KEY);

        if (is_array($pending) && ! empty($pending['path'])) {
            Storage::disk('local')->delete($pending['path']);
        }

        $request->session()->forget(self::IMPORT_SESSION_KEY);
    }

    public function create(): Response
    {
        return Inertia::render('Employees/Form', [
            'employee' => $this->emptyEmployee(),
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreEmployeeRequest $request, ZktDeviceUserSyncService $deviceUserSyncService): RedirectResponse
    {
        $data = $request->safe()->except('password', 'role_names', 'zkt_location_group_ids', 'profile_photo', 'remove_profile_photo');
        $password = $request->filled('password')
            ? $request->string('password')->value()
            : Str::password(12);

        $employee = null;

        DB::transaction(function () use ($request, $data, $password, &$employee): void {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
            ]);

            $employee = Employee::query()->create([
                ...$data,
                'user_id' => $user->id,
            ]);

            $this->syncUserRoles($request, $user);
            $this->syncProfilePhoto($request, $employee);
        });

        if ($employee && $request->has('zkt_location_group_ids')) {
            $locationGroupIds = $request->input('zkt_location_group_ids', []);
            $employee->zktLocationGroups()->sync($locationGroupIds);

            if ($request->user()?->can('zkt-devices.manage-users')) {
                $employeeId = $employee->id;

                app()->terminating(function () use ($employeeId, $deviceUserSyncService): void {
                    try {
                        $freshEmployee = Employee::query()->find($employeeId);

                        if (! $freshEmployee) {
                            return;
                        }

                        $deviceUserSyncService->syncEmployee(
                            $freshEmployee->fresh(['zktLocationGroups', 'zktDeviceSyncs.device']),
                        );
                    } catch (Throwable $exception) {
                        logger()->warning('Deferred employee machine sync failed.', [
                            'employee_id' => $employeeId,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                });
            }
        }

        $message = $request->filled('password')
            ? 'Employee and login account created successfully.'
            : "Employee created successfully. Temporary login password: {$password}";

        $redirect = redirect()
            ->route('employees.index')
            ->with('success', $message);

        return $redirect;
    }

    public function show(Employee $employee): Response
    {
        $employee->load([
            'grade.level.group',
            'grade.level.node.group',
            'manager',
            'user',
            'directReports',
            'zktLocationGroups:id,name,code,is_active',
            'zktDeviceSyncs.device:id,name,location',
        ]);

        return Inertia::render('Employees/Show', [
            'employee' => $this->formatEmployee($employee, includeRelations: true),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('Employees/Form', [
            'employee' => $this->formatEmployee($employee),
            ...$this->formOptions($employee),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee, ZktDeviceUserSyncService $deviceUserSyncService): RedirectResponse
    {
        $data = $request->safe()->except('password', 'role_names', 'zkt_location_group_ids', 'profile_photo', 'remove_profile_photo');
        $password = $request->filled('password')
            ? $request->string('password')->value()
            : null;

        $message = null;

        DB::transaction(function () use ($request, $employee, $data, $password, &$message): void {
            $employee->update($data);
            $this->syncProfilePhoto($request, $employee);

            if ($employee->user) {
                $employee->user->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    ...($password ? ['password' => $password] : []),
                ]);

                $this->syncUserRoles($request, $employee->user);

                return;
            }

            $generatedPassword = $password ?? Str::password(12);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $generatedPassword,
            ]);

            $employee->update(['user_id' => $user->id]);
            $this->syncUserRoles($request, $user);

            if (! $password) {
                $message = "Employee updated. Login account created. Temporary password: {$generatedPassword}";
            }
        });

        if ($request->has('zkt_location_group_ids')) {
            $locationGroupIds = $request->input('zkt_location_group_ids', []);
            $employee->zktLocationGroups()->sync($locationGroupIds);

            if ($request->user()?->can('zkt-devices.manage-users')) {
                $employeeId = $employee->id;

                app()->terminating(function () use ($employeeId, $deviceUserSyncService): void {
                    try {
                        $freshEmployee = Employee::query()->find($employeeId);

                        if (! $freshEmployee) {
                            return;
                        }

                        $deviceUserSyncService->syncEmployee(
                            $freshEmployee->fresh(['zktLocationGroups', 'zktDeviceSyncs.device']),
                        );
                    } catch (Throwable $exception) {
                        logger()->warning('Deferred employee machine sync failed.', [
                            'employee_id' => $employeeId,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                });
            }
        }

        $message ??= $password
            ? 'Employee updated and login password reset successfully.'
            : 'Employee updated successfully.';

        $redirect = redirect()
            ->route('employees.show', $employee)
            ->with('success', $message);

        return $redirect;
    }

    public function destroy(Employee $employee, ZktDeviceUserSyncService $deviceUserSyncService): RedirectResponse
    {
        if ($employee->directReports()->exists()) {
            return back()->with('error', 'Cannot delete an employee who manages other employees.');
        }

        $deviceUserSyncService->removeEmployeeFromAllDevices($employee);
        $this->deleteProfilePhoto($employee);

        DB::transaction(function () use ($employee): void {
            $user = $employee->user;
            $employee->delete();
            $user?->delete();
        });

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee and login account deleted successfully.');
    }

    public function syncDevices(Employee $employee, ZktDeviceUserSyncService $deviceUserSyncService): RedirectResponse
    {
        $results = $deviceUserSyncService->syncEmployee($employee->fresh(['zktLocationGroups', 'zktDeviceSyncs.device']));
        $failed = collect($results)->where('status', 'failed')->count();
        $synced = collect($results)->where('status', 'synced')->count();

        if ($failed > 0) {
            return back()->with(
                'error',
                "Synced {$synced} machine profile(s), but {$failed} failed. Review device sync status below.",
            );
        }

        return back()->with('success', "Synced {$synced} machine profile(s) successfully.");
    }

    public function pullDeviceCredentials(Employee $employee, ZktDeviceUserSyncService $deviceUserSyncService): RedirectResponse
    {
        $result = $deviceUserSyncService->pullEmployeeCredentialsFromDevices($employee->fresh());

        return match ($result['status']) {
            'updated' => back()->with('success', $result['message']),
            'unchanged' => back()->with('success', $result['message']),
            'not_found' => back()->with('error', $result['message']),
            default => back()->with('error', $result['message']),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyEmployee(): array
    {
        return [
            'id' => null,
            'staff_id' => '',
            'name' => '',
            'profile_photo_url' => null,
            'national_id' => '',
            'email' => '',
            'mobile_number' => '',
            'joined_date' => now()->toDateString(),
            'gender' => Gender::Male->value,
            'grade_id' => null,
            'device_privilege' => ZktDevicePrivilege::Employee->value,
            'device_card_number' => null,
            'device_password' => null,
            'manager_id' => null,
            'is_active' => true,
            'works_saturday' => false,
            'duty_type' => DutyType::Normal->value,
            'uses_custom_duty_times' => false,
            'custom_duty_start_time' => null,
            'custom_duty_end_time' => null,
            'custom_grace_minutes' => null,
            'custom_saturday_duty_start_time' => null,
            'custom_saturday_duty_end_time' => null,
            'custom_saturday_grace_minutes' => null,
            'role_names' => [],
            'zkt_location_group_ids' => [],
            'current_address' => '',
            'permanent_address' => '',
            'ext_no' => '',
            'personal_email' => '',
            'office_email' => '',
            'emergency_contact_name' => '',
            'emergency_contact_number' => '',
            'marital_status' => null,
            'blood_group' => null,
            'date_of_birth' => null,
            'nationality' => '',
            'religion' => '',
            'work_location' => '',
            'qualification' => '',
            'employment_type' => null,
            'bank_name' => '',
            'account_name' => '',
            'account_no' => '',
            'length_of_service_label' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(?Employee $employee = null): array
    {
        return [
            'grades' => app(CompanyStructureService::class)->gradeOptions(),
            'managers' => Employee::query()
                ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'staff_id'])
                ->map(fn (Employee $manager) => [
                    'id' => $manager->id,
                    'label' => "{$manager->name} ({$manager->staff_id})",
                ]),
            'genders' => Gender::options(),
            'maritalStatuses' => MaritalStatus::options(),
            'bloodGroups' => BloodGroup::options(),
            'employmentTypes' => EmploymentType::options(),
            'dutyTypes' => DutyType::options(),
            'banks' => Bank::options(),
            'nationalities' => Nationality::options(),
            'devicePrivileges' => ZktDevicePrivilege::options(),
            'roles' => $this->assignableRoles($employee),
            'canAssignRoles' => $this->canAssignRoles(),
            'locationGroups' => ZktLocationGroup::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (ZktLocationGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'code' => $group->code,
                ]),
        ];
    }

    protected function canAssignRoles(): bool
    {
        return (bool) request()->user()?->can('users.assign-roles');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    protected function assignableRoles(?Employee $employee = null): array
    {
        if (! $this->canAssignRoles()) {
            return [];
        }

        return Role::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->values()
            ->all();
    }

    protected function syncUserRoles(Request $request, User $user): void
    {
        if (! $request->user()?->can('users.assign-roles')) {
            return;
        }

        $user->syncRoles($request->input('role_names', []));
    }

    protected function syncProfilePhoto(Request $request, Employee $employee): void
    {
        if ($request->boolean('remove_profile_photo')) {
            $this->deleteProfilePhoto($employee);

            return;
        }

        if (! $request->hasFile('profile_photo')) {
            return;
        }

        $this->deleteProfilePhoto($employee);

        $employee->update([
            'profile_photo_path' => $request->file('profile_photo')->store('employee-photos', 'public'),
        ]);
    }

    protected function deleteProfilePhoto(Employee $employee): void
    {
        if (! $employee->profile_photo_path) {
            return;
        }

        Storage::disk('public')->delete($employee->profile_photo_path);

        if ($employee->exists) {
            $employee->update(['profile_photo_path' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatEmployee(Employee $employee, bool $includeRelations = false): array
    {
        $employee->loadMissing([
            'grade.level.group',
            'grade.level.node.group',
            'grade.level.node.parent',
            'manager:id,name,staff_id',
            'user:id,name,email',
            'zktLocationGroups:id,name,code,is_active',
        ]);
        $employee->user?->loadMissing('roles:id,name');

        $path = $employee->grade?->resolvePath();

        $data = [
            'id' => $employee->id,
            'staff_id' => $employee->staff_id,
            'name' => $employee->name,
            'profile_photo_url' => $employee->profilePhotoUrl(),
            'national_id' => $employee->national_id,
            'email' => $employee->email,
            'mobile_number' => $employee->mobile_number,
            'joined_date' => $employee->joined_date?->toDateString(),
            'gender' => $employee->gender->value,
            'gender_label' => $employee->gender->label(),
            'grade_id' => $employee->grade_id,
            'grade' => $employee->grade ? [
                'id' => $employee->grade->id,
                'label' => $employee->grade->label(),
                'grade' => $employee->grade->grade,
                'title' => $employee->grade->title,
                'path_label' => $path['path_label'] ?? $employee->grade->label(),
                'group' => $path['group'] ?? null,
                'node' => $path['node'] ?? null,
                'level' => $path['level'] ?? null,
            ] : null,
            'department' => $path === null
                ? null
                : ($path['node'] ?? ($path['group'] ? [
                    'id' => $path['group']['id'],
                    'name' => $path['group']['name'],
                ] : null)),
            'device_privilege' => $employee->device_privilege->value,
            'device_privilege_label' => $employee->device_privilege->label(),
            'device_card_number' => $employee->device_card_number,
            'has_device_password' => filled($employee->device_password),
            'user_id' => $employee->user_id,
            'has_login' => $employee->user_id !== null,
            'user' => $employee->user ? [
                'id' => $employee->user->id,
                'name' => $employee->user->name,
                'email' => $employee->user->email,
            ] : null,
            'manager_id' => $employee->manager_id,
            'manager' => $employee->manager ? [
                'id' => $employee->manager->id,
                'name' => $employee->manager->name,
                'staff_id' => $employee->manager->staff_id,
            ] : null,
            'is_active' => $employee->is_active,
            'works_saturday' => $employee->works_saturday,
            'duty_type' => $employee->duty_type->value,
            'duty_type_label' => $employee->duty_type->label(),
            'uses_custom_duty_times' => $employee->uses_custom_duty_times,
            'custom_duty_start_time' => $employee->formatCustomTimeForInput($employee->custom_duty_start_time),
            'custom_duty_end_time' => $employee->formatCustomTimeForInput($employee->custom_duty_end_time),
            'custom_grace_minutes' => $employee->custom_grace_minutes,
            'custom_saturday_duty_start_time' => $employee->formatCustomTimeForInput($employee->custom_saturday_duty_start_time),
            'custom_saturday_duty_end_time' => $employee->formatCustomTimeForInput($employee->custom_saturday_duty_end_time),
            'custom_saturday_grace_minutes' => $employee->custom_saturday_grace_minutes,
            'role_names' => $employee->user?->getRoleNames()->values()->all() ?? [],
            'zkt_location_group_ids' => $employee->relationLoaded('zktLocationGroups')
                ? $employee->zktLocationGroups->pluck('id')->values()->all()
                : [],
            'zkt_location_groups' => $employee->relationLoaded('zktLocationGroups')
                ? $employee->zktLocationGroups->map(fn (ZktLocationGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'code' => $group->code,
                    'is_active' => $group->is_active,
                ])->values()->all()
                : [],
            'zkt_device_syncs' => $employee->relationLoaded('zktDeviceSyncs')
                ? $employee->zktDeviceSyncs->map(fn ($sync) => $sync->toPresentationArray())->values()->all()
                : [],
            'current_address' => $employee->current_address,
            'permanent_address' => $employee->permanent_address,
            'ext_no' => $employee->ext_no,
            'personal_email' => $employee->personal_email,
            'office_email' => $employee->office_email,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_number' => $employee->emergency_contact_number,
            'marital_status' => $employee->marital_status?->value,
            'marital_status_label' => $employee->marital_status?->label(),
            'blood_group' => $employee->blood_group?->value,
            'blood_group_label' => $employee->blood_group?->label(),
            'date_of_birth' => $employee->date_of_birth?->toDateString(),
            'nationality' => $employee->nationality,
            'religion' => $employee->religion,
            'work_location' => $employee->work_location,
            'qualification' => $employee->qualification,
            'employment_type' => $employee->employment_type?->value,
            'employment_type_label' => $employee->employment_type?->label(),
            'bank_name' => $employee->bank_name,
            'bank_name_label' => Bank::labelFor($employee->bank_name),
            'account_name' => $employee->account_name,
            'account_no' => $employee->account_no,
            'length_of_service_label' => $employee->lengthOfServiceLabel(),
        ];

        if ($includeRelations) {
            $data['direct_reports'] = $employee->directReports
                ->map(fn (Employee $report) => [
                    'id' => $report->id,
                    'name' => $report->name,
                    'staff_id' => $report->staff_id,
                ])
                ->values()
                ->all();
        }

        return $data;
    }
}
