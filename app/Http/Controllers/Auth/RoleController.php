<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;

class RoleController extends Controller
{
    function __construct()
    {
        $this->middleware('permission:role-list', ['only' => ['index']]);
        $this->middleware('permission:role-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:role-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:role-delete', ['only' => ['destroy']]);
    }

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

        return Inertia::render('roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function getData()
    {
        if (auth()->user()->hasRole('Super Admin'))
            return response()->json(Role::all(), 200);
        else
            return response()->json(Role::where('name', '!=', 'Super Admin')->get(), 200);
    }

    public function create()
    {
        $permissions = Permission::orderBy('name')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
        ])->toArray();

        return Inertia::render('roles/Create', [
            'permissions' => $permissions,
        ]);
    }


    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|unique:roles,name',
//            'permissions' => 'required',
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

    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return Inertia::render('roles/Show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->toArray(),
            ],
        ]);
    }

    public function edit($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $allPermissions = Permission::orderBy('name')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
        ])->toArray();

        return Inertia::render('roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->toArray(),
            ],
            'allPermissions' => $allPermissions,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
        ]);

//        dd($request->all());

        $role = Role::findOrFail($id);
        $role->name = $request->input('name');
        $role->guard_name = $request->input('guard');
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
