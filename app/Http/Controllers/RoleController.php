<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Support\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Roles/Index', [
            'roles' => Role::query()
                ->withCount('permissions', 'users')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions_count' => $role->permissions_count,
                    'users_count' => $role->users_count,
                    'is_system' => $role->name === PermissionRegistry::superAdminRole(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Form', [
            'role' => $this->emptyRole(),
            'permissionGroups' => PermissionRegistry::grouped(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions'));

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions:id,name');

        return Inertia::render('Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values()->all(),
                'is_system' => $role->name === PermissionRegistry::superAdminRole(),
            ],
            'permissionGroups' => PermissionRegistry::grouped(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->name === PermissionRegistry::superAdminRole()) {
            $role->syncPermissions(PermissionRegistry::all());

            return redirect()
                ->route('roles.index')
                ->with('success', 'Super Admin always has full access.');
        }

        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions'));

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === PermissionRegistry::superAdminRole()) {
            return back()->with('error', 'The Super Admin role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Reassign users before deleting this role.');
        }

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyRole(): array
    {
        return [
            'id' => null,
            'name' => '',
            'permissions' => [],
            'is_system' => false,
        ];
    }
}
