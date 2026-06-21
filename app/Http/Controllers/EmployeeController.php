<?php

namespace App\Http\Controllers;

use App\Enums\DutyType;
use App\Enums\Gender;
use App\Enums\ZktDevicePrivilege;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use App\Models\ZktLocationGroup;
use App\Services\Zkt\ZktDeviceUserSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Employees/Index', [
            'employees' => Employee::query()
                ->with(['department:id,name', 'manager:id,name,staff_id', 'user:id,name,email'])
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $employee) => $this->formatEmployee($employee)),
        ]);
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
        $data = $request->safe()->except('password', 'role_names', 'zkt_location_group_ids');
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
        });

        if ($employee && $request->user()?->can('zkt-devices.manage-users')) {
            $deviceUserSyncService->syncEmployeeLocationGroups(
                $employee,
                $request->input('zkt_location_group_ids', []),
            );
        } elseif ($employee && $request->filled('zkt_location_group_ids')) {
            $employee->zktLocationGroups()->sync($request->input('zkt_location_group_ids', []));
        }

        $message = $request->filled('password')
            ? 'Employee and login account created successfully.'
            : "Employee created successfully. Temporary login password: {$password}";

        return redirect()
            ->route('employees.index')
            ->with('success', $message);
    }

    public function show(Employee $employee): Response
    {
        $employee->load([
            'department',
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
        $data = $request->safe()->except('password', 'role_names', 'zkt_location_group_ids');
        $password = $request->filled('password')
            ? $request->string('password')->value()
            : null;

        $message = null;

        DB::transaction(function () use ($request, $employee, $data, $password, &$message): void {
            $employee->update($data);

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

        if ($request->user()?->can('zkt-devices.manage-users')) {
            $deviceUserSyncService->syncEmployeeLocationGroups(
                $employee,
                $request->input('zkt_location_group_ids', []),
            );
        } elseif ($request->has('zkt_location_group_ids')) {
            $employee->zktLocationGroups()->sync($request->input('zkt_location_group_ids', []));
        }

        $message ??= $password
            ? 'Employee updated and login password reset successfully.'
            : 'Employee updated successfully.';

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', $message);
    }

    public function destroy(Employee $employee, ZktDeviceUserSyncService $deviceUserSyncService): RedirectResponse
    {
        if ($employee->directReports()->exists()) {
            return back()->with('error', 'Cannot delete an employee who manages other employees.');
        }

        if ($employee->headedDepartments()->exists()) {
            return back()->with('error', 'Cannot delete an employee assigned as a department head.');
        }

        $deviceUserSyncService->removeEmployeeFromAllDevices($employee);

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
            'national_id' => '',
            'email' => '',
            'mobile_number' => '',
            'joined_date' => now()->toDateString(),
            'gender' => Gender::Male->value,
            'department_id' => null,
            'designation_id' => null,
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(?Employee $employee = null): array
    {
        return [
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                ]),
            'designations' => Designation::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Designation $designation) => [
                    'id' => $designation->id,
                    'name' => $designation->name,
                ]),
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
            'dutyTypes' => DutyType::options(),
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

    /**
     * @return array<string, mixed>
     */
    protected function formatEmployee(Employee $employee, bool $includeRelations = false): array
    {
        $employee->loadMissing(['department:id,name', 'designation:id,name', 'manager:id,name,staff_id', 'user:id,name,email', 'zktLocationGroups:id,name,code,is_active']);
        $employee->user?->loadMissing('roles:id,name');

        $data = [
            'id' => $employee->id,
            'staff_id' => $employee->staff_id,
            'name' => $employee->name,
            'national_id' => $employee->national_id,
            'email' => $employee->email,
            'mobile_number' => $employee->mobile_number,
            'joined_date' => $employee->joined_date?->toDateString(),
            'gender' => $employee->gender->value,
            'gender_label' => $employee->gender->label(),
            'department_id' => $employee->department_id,
            'department' => $employee->department ? [
                'id' => $employee->department->id,
                'name' => $employee->department->name,
            ] : null,
            'designation_id' => $employee->designation_id,
            'designation' => $employee->designation ? [
                'id' => $employee->designation->id,
                'name' => $employee->designation->name,
            ] : null,
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
