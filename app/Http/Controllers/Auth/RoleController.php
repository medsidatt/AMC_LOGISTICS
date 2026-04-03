<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if ($request->ajax()) {
            return Datatables::of(Role::query())
                ->addColumn('action', function ($role) {
                    $actions = [
                        [
                            'label' => __('View'),
                            'href' => route('roles.show', $role->id),
                            'permission' => 'role-show',
                        ],
                        [
                            'label' => __('Edit'),
                            'href' => route('roles.edit', $role->id),
                            'permission' => 'role-edit',
                        ],
                        [
                            'label' => __('Delete'),
                            'onclick' => 'confirmDelete(\'' . route('roles.destroy', $role->id) . '\')',
                            'permission' => 'role-delete',
                        ],
                    ];
                    return view('components.buttons.action', compact('actions'));
                })
                ->addColumn('permissions', function ($role) {
                    $permissions = Permission::get();
                    $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id", $role->id)
                        ->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
                        ->all();
                    return view('pages.roles.permissions', compact('permissions', 'rolePermissions', 'role'));
                })
                // DT_RowIndex
                ->addIndexColumn()
                ->rawColumns(['action', 'permissions'])
                ->make(true);
        }

        $breadcrumbs = [
            ['label' => __('Roles'), 'url' => ''],
        ];
        $actions = [
            [
                'label' => __('Create'),
                'url' => route('roles.create'),
                'permission' => 'role-create',
            ]
        ];

        return view('pages.roles.index', [
            'breadcrumbs' => $breadcrumbs,
            'actions' => $actions,
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
        $permission = Permission::get();
        $breadcrumbs = [
            ['label' => __('user.role_management'), 'url' => route('roles.index')],
            ['label' => __('user.create_role'), 'url' => '#'],
        ];
        return view('pages.roles.create', compact('permission', 'breadcrumbs'));
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
        $role = Role::find($id);
        $permission = Permission::get();
        $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id", $id)
            ->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
            ->all();

        return view('pages.roles.show',
            [
                'breadcrumbs' => [
                    ['url' => route('roles.index'), 'label' => __('Roles')],
                    ['url' => '#', 'label' => $role->name]
                ],
                'actions' => [],
                'role' => $role,
                'permission' => $permission,
                'rolePermissions' => $rolePermissions
            ]
        );
    }

    public function edit($id)
    {
        $role = Role::find($id);
        $permission = Permission::get();
        $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id", $id)
            ->pluck('role_has_permissions.permission_id', 'role_has_permissions.permission_id')
            ->all();
        $breadcrumbs = [
            ['label' => 'Roles', 'url' => route('roles.index')],
            ['label' => 'Edit', 'url' => '#'],
        ];

        return view('pages.roles.edit',
            [
                'role' => $role,
                'permission' => $permission,
                'rolePermissions' => $rolePermissions,
                'breadcrumbs' => $breadcrumbs,
            ]
        );
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
        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }
}
