<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\Invitation;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_suspended' => $user->is_suspended,
                'roles' => $user->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                ])->toArray(),
                'created_at' => $user->created_at?->format('Y-m-d'),
            ]);

        $roles = Role::orderBy('name')->get(['id', 'name'])->toArray();

        return Inertia::render('users/Index', [
            'users' => $users,
            'roles' => $roles,
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

        if (! empty($request->roles)) {
            $roles = Role::query()->whereIn('id', $request->roles)->pluck('name');
            $user->syncRoles($roles);
        }

        return redirect()->back()->with('success', 'User created successfully.');
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

        $user = User::find($id);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        $roles = Role::whereIn('id', $request->roles)->get();

        $user->syncRoles($roles);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    //destroy

    public function destroy($id)
    {
        User::find($id)->delete();
        return redirect()->back()->with('success', 'User deleted successfully.');
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
        return view('pages.auth.profile', [
            'employee' => null
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
