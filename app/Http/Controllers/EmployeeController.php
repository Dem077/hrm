<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
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

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('password', 'role_names');
        $password = $request->filled('password')
            ? $request->string('password')->value()
            : Str::password(12);

        DB::transaction(function () use ($request, $data, $password): void {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
            ]);

            Employee::query()->create([
                ...$data,
                'user_id' => $user->id,
            ]);

            $this->syncUserRoles($request, $user);
        });

        $message = $request->filled('password')
            ? 'Employee and login account created successfully.'
            : "Employee created successfully. Temporary login password: {$password}";

        return redirect()
            ->route('employees.index')
            ->with('success', $message);
    }

    public function show(Employee $employee): Response
    {
        $employee->load(['department', 'manager', 'user', 'directReports']);

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

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->safe()->except('password', 'role_names');
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

        $message ??= $password
            ? 'Employee updated and login password reset successfully.'
            : 'Employee updated successfully.';

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', $message);
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->directReports()->exists()) {
            return back()->with('error', 'Cannot delete an employee who manages other employees.');
        }

        if ($employee->headedDepartments()->exists()) {
            return back()->with('error', 'Cannot delete an employee assigned as a department head.');
        }

        DB::transaction(function () use ($employee): void {
            $user = $employee->user;
            $employee->delete();
            $user?->delete();
        });

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee and login account deleted successfully.');
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
            'manager_id' => null,
            'is_active' => true,
            'works_saturday' => false,
            'uses_custom_duty_times' => false,
            'custom_duty_start_time' => null,
            'custom_duty_end_time' => null,
            'custom_grace_minutes' => null,
            'custom_saturday_duty_start_time' => null,
            'custom_saturday_duty_end_time' => null,
            'custom_saturday_grace_minutes' => null,
            'role_names' => [],
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
            'roles' => $this->assignableRoles($employee),
            'canAssignRoles' => $this->canAssignRoles(),
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
        $employee->loadMissing(['department:id,name', 'manager:id,name,staff_id', 'user:id,name,email']);
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
            'uses_custom_duty_times' => $employee->uses_custom_duty_times,
            'custom_duty_start_time' => $employee->formatCustomTimeForInput($employee->custom_duty_start_time),
            'custom_duty_end_time' => $employee->formatCustomTimeForInput($employee->custom_duty_end_time),
            'custom_grace_minutes' => $employee->custom_grace_minutes,
            'custom_saturday_duty_start_time' => $employee->formatCustomTimeForInput($employee->custom_saturday_duty_start_time),
            'custom_saturday_duty_end_time' => $employee->formatCustomTimeForInput($employee->custom_saturday_duty_end_time),
            'custom_saturday_grace_minutes' => $employee->custom_saturday_grace_minutes,
            'role_names' => $employee->user?->getRoleNames()->values()->all() ?? [],
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
