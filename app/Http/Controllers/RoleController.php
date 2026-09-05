<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(public RoleService $roleService) {}

    public function index(Request $request)
    {
        $this->authorizeAdmin('roles.view');

        $search = (string) $request->input('search', '');
        $sortBy = in_array($request->input('sort_column'), ['id', 'name', 'created_at']) ? $request->input('sort_column') : 'created_at';
        $sortDir = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $roles = $this->roleService->getPaginatedRoles(search: $search, sortBy: $sortBy, sortDir: $sortDir);

        return Inertia::render('Roles/RolesPage', [
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'sort_column' => $sortBy,
                'sort_direction' => $sortDir,
            ],
            'permissions' => Permission::query()->select('id', 'name')->get(),
        ]);
    }

    public function store(RoleRequest $request)
    {
        $this->roleService->store(data: $request->validated());

        return redirect()->back()->with('success', 'تم الإضافة بنجاح');
    }

    public function update(RoleRequest $request, Role $role)
    {
        $this->roleService->update(role: $role, data: $request->validated());

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(Role $role)
    {
        $this->authorizeAdmin('roles.delete');

        $this->roleService->delete(role: $role);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
