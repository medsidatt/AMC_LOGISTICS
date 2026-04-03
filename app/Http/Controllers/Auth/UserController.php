<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\Invitation;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;

class UserController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:user-list', ['only' => ['index']]);
        $this->middleware('permission:user-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:user-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:user-delete', ['only' => ['destroy']]);
        $this->middleware('permission:user-invitation', ['only' => ['resendInvitation']]);
        $this->middleware('permission:user-suspend', ['only' => ['suspend']]);
        $this->middleware('permission:user-change-password', ['only' => ['changePassword', 'updatePassword']]);
    }

    // index
    public function index()
    {
        if (\request()->ajax()) {

            $users = User::all();

            return DataTables::of($users)
                ->addIndexColumn()
                ->addColumn('action', function ($user) {
                    $actions = [
                        [
                            'label' => __('system.edit'),
                            'onclick' => 'openInModal({ link: \'' . route('users.edit', $user->id) . '\', size: \'md\' })',
                            'permission' => 'user-edit'
                        ],
                        [
                            'label' => __('system.show'),
                            'onclick' => 'openInModal({ link: \'' . route('users.show', $user->id) . '\', size: \'md\' })',
                            'permission' => 'user-show'
                        ],
                        [
                            'label' => $user->is_suspended ? 'Activer' : 'Suspendre',
                            'onclick' => 'confirmSuspend(\'' . route('users.suspend', $user->id) . '\')',
                            'permission' => 'user-suspend'
                        ],
                        [
                            'label' => 'Modifier mot de passe',
                            'onclick' => 'openInModal({ link: \'' . route('users.change-password', $user->id) . '\', size: \'md\' })',
                            'permission' => 'user-change-password'
                        ],
                        [
                            'label' => 'Supprimer',
                            'onclick' => 'confirmDelete(\'' . route('users.destroy', $user->id) . '\')',
                            'permission' => 'user-delete'
                        ],
                    ];
                    return view('components.buttons.action', ['actions' => $actions]);
                })
                ->addColumn('status', function ($user) {
                    return view('pages.users._status', compact('user'));
                })
                ->editColumn('id', function ($user) {
                    return $user->employee ? $user->employee->badge_number : $user->id;
                })
                ->rawColumns(['action', 'status'])
                ->make(true);
        }

        $actions = [
            [
                'label' => __('system.create'),
                'onclick' => 'openInModal({ link: \'' . route('users.create') . '\', size: \'md\' })',
                'permission' => 'user-create'
            ],
            [
                'label' => __('user.invite_user'),
                'onclick' => 'openInModal({ link: \'' . route('invitation.create') . '\', size: \'md\' })',
                'permission' => 'user-invitation'
            ]
        ];

        return view('pages.users.index', [
            'actions' => $actions
        ]);
    }

    //create
    public function create()
    {
        Role::firstOrCreate(['name' => 'Driver'], ['guard_name' => 'web']);
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);

        return view('pages.users.create', compact('roles'));
    }

    //store
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => ['required', 'unique:users,email'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = User::firstOrCreate([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt('password'),
        ]);

        $user->employee()->firstOrCreate(
            [
                'email' => $request->email,
            ],
            [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => '0000000000',
                'address' => 'address',
            ]);

        if (! empty($request->roles)) {
            $roles = Role::query()->whereIn('id', $request->roles)->pluck('name');
            $user->syncRoles($roles);
        }

        return response()->json([
            'message' => 'User created successfully.',
            'success' => true
        ]);
    }

    //edit
    public function edit($id)
    {
        $user = User::find($id);
        Role::firstOrCreate(['name' => 'Driver'], ['guard_name' => 'web']);
        $roles = Role::all();
        return view('pages.users.edit', compact('user', 'roles'));
    }

    //update
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => ['required', 'unique:users,email,' . $id],
            'roles' => ['required', 'array']
        ]);

//        dd($request->all());

        $user = User::find($id);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        $roles = Role::whereIn('id', $request->roles)->get();

        $user->syncRoles($roles);

        return response()->json([
            'message' => 'User updated successfully.',
            'success' => true
        ], 200);
    }

    //destroy

    public function destroy($id)
    {
        User::find($id)->delete();
        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.'
        ]);
    }

    //show
    public function show($id)
    {
        $user = User::find($id);
        return view('pages.users.show', compact('user'));
    }

    // suspend
    public function suspend($id)
    {
        $user = User::find($id);
        $user->is_suspended = !$user->is_suspended;
        $user->save();
        return redirect()->back()->with([
            'success' => 'User status updated successfully'
        ]);
    }

    // changePassword
    public function changePassword($id)
    {
        $user = User::find($id);
        return view('pages.users.change-password', [
            'user' => $user
        ]);
    }

    // updatePassword
    public function updatePassword(Request $request)
    {
        $this->validate($request, [
            'old_password' => 'required',
            'password' => 'required|confirmed',
        ]);

        $user = auth()->user();
        if (!\Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'Old password is not correct'
            ], 422);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json([
            'success' => 'Password updated successfully'
        ], 201);
    }

    // changeUserPassword
    public function changeUserPassword(Request $request, $id)
    {
        $this->validate($request, [
            'password' => 'required|confirmed',
        ]);

        $user = User::find($id);
        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully.'
        ], 201);
    }

    // account
    public function account()
    {
        return view('pages.auth.account');
    }

    // profile
    public function profile()
    {
        $employee = auth()->user()->employee;
        return view('pages.auth.profile', [
            'employee' => $employee
        ]);
    }

    // updateAccount
    public function updateAccount(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => ['required', 'unique:users,email,' . auth()->id()],
        ]);

        $user = auth()->user();

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Account updated successfully.',
            'success' => true
        ]);
    }
}
