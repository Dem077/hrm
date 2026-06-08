<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Departments/Index', [
            'departments' => Department::query()
                ->with('headEmployee:id,name,staff_id')
                ->withCount('employees')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department) => $this->formatDepartment($department)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Departments/Form', [
            'department' => $this->emptyDepartment(),
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->validated());

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function show(Department $department): Response
    {
        $department->load([
            'headEmployee',
            'employees' => fn ($query) => $query->with('manager:id,name,staff_id')->orderBy('name'),
        ]);

        return Inertia::render('Departments/Show', [
            'department' => $this->formatDepartment($department, includeRelations: true),
        ]);
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('Departments/Form', [
            'department' => $this->formatDepartment($department),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()
            ->route('departments.show', $department)
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            return back()->with('error', 'Reassign employees before deleting this department.');
        }

        $department->delete();

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyDepartment(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'description' => '',
            'head_employee_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'heads' => Employee::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'staff_id'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'label' => "{$employee->name} ({$employee->staff_id})",
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatDepartment(Department $department, bool $includeRelations = false): array
    {
        $department->loadMissing('headEmployee:id,name,staff_id');

        $data = [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'description' => $department->description,
            'head_employee_id' => $department->head_employee_id,
            'head_employee' => $department->headEmployee ? [
                'id' => $department->headEmployee->id,
                'name' => $department->headEmployee->name,
                'staff_id' => $department->headEmployee->staff_id,
            ] : null,
            'is_active' => $department->is_active,
            'sort_order' => $department->sort_order,
            'employees_count' => $department->employees_count ?? $department->employees()->count(),
        ];

        if ($includeRelations) {
            $data['employees'] = $department->employees
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'staff_id' => $employee->staff_id,
                    'name' => $employee->name,
                    'manager' => $employee->manager ? [
                        'id' => $employee->manager->id,
                        'name' => $employee->manager->name,
                    ] : null,
                ])
                ->values()
                ->all();
        }

        return $data;
    }
}
