<?php

namespace App\Http\Controllers\Auth;

use App\Http\Concerns\AssignableRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Mail\InvitationMail;
use App\Models\Auth\Invitation;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AssignableRoles;

    public function __construct()
    {
        $this->middleware('permission:user-list', ['only' => ['index']]);
        $this->middleware('permission:user-create', ['only' => ['store']]);
        $this->middleware('permission:user-edit', ['only' => ['update']]);
        $this->middleware('permission:user-delete', ['only' => ['destroy']]);
        $this->middleware('permission:user-suspend', ['only' => ['suspend']]);
    }

    /**
     * Block management actions that target the current user (self-suspend,
     * self-delete, self role-change) or that target a Super Admin when the
     * caller is not one. Returns a redirect on violation, or null when allowed.
     */
    private function guardManage(User $target)
    {
        if (auth()->id() === $target->id) {
            return redirect()->back()->with('error', 'Vous ne pouvez pas effectuer cette action sur votre propre compte.');
        }

        if ($target->hasRole('Super Admin') && ! auth()->user()->hasRole('Super Admin')) {
            return redirect()->back()->with('error', "Action non autorisée sur un compte Super Admin.");
        }

        return null;
    }

    // index
    public function index()
    {
        $users = User::query()
            ->with(['roles.permissions', 'permissions'])
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
                // Permissions the user holds directly (the editable "extras").
                'direct_permissions' => $user->getDirectPermissions()->pluck('name')->values()->toArray(),
                // Permissions inherited from roles (shown locked in the editor).
                'role_permissions' => $user->getPermissionsViaRoles()->pluck('name')->unique()->values()->toArray(),
                'created_at' => $user->created_at?->format('d/m/Y'),
            ]);

        $roles = Role::orderBy('name')->get(['id', 'name'])->toArray();
        $allPermissions = Permission::orderBy('name')->get(['id', 'name'])->toArray();

        return Inertia::render('users/Index', [
            'users' => $users,
            'roles' => $roles,
            'allPermissions' => $allPermissions,
        ]);
    }

    //store
    public function store(StoreUserRequest $request)
    {
        $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);

        DB::beginTransaction();
        try {
            // Password is cast to 'hashed' on the model, so pass it in plain.
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $plainPassword,
                'must_change_password' => true,
            ]);

            if (! empty($request->roles)) {
                $roles = Role::query()->whereIn('id', $request->roles)->pluck('name');
                $user->syncRoles($roles);
            }

            // Email the generated credentials so the account is usable without
            // a hardcoded default password.
            Mail::to($user->email)->send(new InvitationMail(
                new Invitation([
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_name' => $user->getRoleNames()->first(),
                ]),
                $plainPassword,
            ));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->with('error', "Échec de la création de l'utilisateur.");
        }

        return redirect()->back()->with('success', 'Utilisateur créé. Les identifiants ont été envoyés par email.');
    }

    //update
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::findOrFail($id);

        if ($deny = $this->guardManage($user)) {
            return $deny;
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        $roles = Role::whereIn('id', $request->roles)->get();

        $user->syncRoles($roles);

        // Direct permissions ("extras" on top of the role). syncPermissions
        // replaces the user's direct permissions with exactly this set; role
        // permissions are unaffected.
        $permissionNames = Permission::whereIn('id', $request->input('permissions', []))
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        $user->syncPermissions($permissionNames);

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    //destroy

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ($deny = $this->guardManage($user)) {
            return $deny;
        }

        $user->delete();
        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    // suspend
    public function suspend($id)
    {
        $user = User::findOrFail($id);

        if ($deny = $this->guardManage($user)) {
            return $deny;
        }

        $user->is_suspended = !$user->is_suspended;
        $user->save();
        return redirect()->back()->with([
            'success' => 'User status updated successfully'
        ]);
    }

    // updatePassword
    public function updatePassword(Request $request)
    {
        $this->validate($request, [
            'old_password' => 'required',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = auth()->user();
        if (!\Hash::check($request->old_password, $user->password)) {
            return redirect()->back()->withErrors(['old_password' => 'L\'ancien mot de passe est incorrect.']);
        }

        // Password is cast to 'hashed' on the model.
        $user->password = $request->password;
        $user->save();

        return redirect()->back()->with('success', 'Mot de passe mis à jour avec succès.');
    }

    // account
    public function account()
    {
        return Inertia::render('account/Profile', [
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ]);
    }

    // profile
    public function profile()
    {
        return Inertia::render('account/Profile', [
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ]);
    }

    // updateAccount
    public function updateAccount(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => ['required', Rule::unique('users', 'email')->ignore(auth()->id())->whereNull('deleted_at')],
        ]);

        $user = auth()->user();

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return redirect()->back()->with('success', 'Compte mis à jour avec succès.');
    }

    public function forcePassword()
    {
        if (!auth()->user()->must_change_password) {
            return redirect()->route('home');
        }

        return Inertia::render('auth/ForcePassword');
    }

    public function forcePasswordUpdate(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = auth()->user();
        // Password is cast to 'hashed' on the model.
        $user->password = $request->password;
        $user->must_change_password = false;
        $user->save();

        return redirect()->route('home')->with('success', 'Mot de passe mis à jour avec succès.');
    }
}
