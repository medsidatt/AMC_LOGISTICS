<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    function __construct()
    {
        $this->middleware('permission:role-list', ['only' => ['index']]);
        $this->middleware('permission:role-create', ['only' => ['store']]);
        $this->middleware('permission:role-edit', ['only' => ['update']]);
        $this->middleware('permission:role-delete', ['only' => ['destroy']]);
    }

    /**
     * Roles SPA workspace. The list, plus everything the create/edit/details
     * drawers need (all permissions + permission meta), ships in one payload so
     * drawers operate entirely from client state — no per-drawer requests.
     */
    public function index(Request $request)
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->paginate(15)
            ->through(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->toArray(),
            ]);

        $allPermissions = Permission::orderBy('name')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
        ])->values();

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'roleDescriptions' => config('permissions_meta.roles', []),
            'permissions' => $allPermissions,
            'permissionMeta' => config('permissions_meta'),
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|unique:roles,name',
        ]);

        $role = Role::firstOrCreate(['name' => $request->input('name')]);
        $permissionIds = $request->input('permissions', []);
        $permissions = Permission::whereIn('id', $permissionIds)
            ->where('guard_name', $role->guard_name)
            ->pluck('name')
            ->toArray();

        $role->syncPermissions($permissions);

        return redirect()->back()
            ->with('success', 'Role created successfully');
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
        ]);

        $role = Role::findOrFail($id);
        $role->name = $request->input('name');
        $role->guard_name = $request->input('guard', 'web'); // Added 'web' as default
        $role->save();

        // Fetch permission names based on the provided IDs
        $permissionIds = $request->input('permissions', []);
        $permissions = Permission::whereIn('id', $permissionIds)
            ->where('guard_name', $role->guard_name)
            ->pluck('name')
            ->toArray();

        $role->syncPermissions($permissions);

        return redirect()->back()
            ->with('success', 'Role updated successfully');
    }

    public function destroy($id)
    {
        Role::findById($id)->delete();
        return redirect()->back()->with('success', 'Role deleted successfully.');
    }
}
