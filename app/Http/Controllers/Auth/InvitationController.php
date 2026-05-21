<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Auth\Invitation;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class InvitationController extends Controller
{

    // index
    public function index()
    {
        $invitations = Invitation::query()
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (Invitation $inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'role_name' => $inv->role_name,
                'is_used' => $inv->is_used,
                'expires_at' => $inv->expires_at,
                'created_at' => $inv->created_at?->format('d/m/Y'),
            ]);

        $roles = $this->assignableRoles()->map(fn ($r) => [
            'name' => $r->name,
        ])->toArray();

        return Inertia::render('invitations/Index', [
            'invitations' => $invitations,
            'roles' => $roles,
        ]);
    }

    public function create()
    {
        $roles = $this->assignableRoles();
        return view('auth.invitations.create', compact('roles'));
    }

    public function edit($id)
    {
        $invitation = Invitation::findOrFail($id);
        $roles = $this->assignableRoles();
        return view('auth.invitations.edit', compact('invitation', 'roles'));
    }

    /**
     * Roles the current user can assign. Only Super Admin can hand out
     * "Super Admin"; everyone else gets every role currently defined in
     * the roles table.
     */
    private function assignableRoles()
    {
        $query = Role::query();

        if (! auth()->user()->hasRole('Super Admin')) {
            $query->where('name', '!=', 'Super Admin');
        }

        return $query->orderBy('name')->get(['name']);
    }

    public function sendInvitation(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
                Rule::unique('invitations', 'email')->whereNull('deleted_at'),
            ],
            'role_name' => 'required|string|exists:roles,name',
        ]);

        DB::beginTransaction();
        try {
            $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);

            // Create the account up front so the invitee can log in
            // directly with the password we email them.
            $user = User::create([
                'name' => Str::before($request->email, '@'),
                'email' => $request->email,
                'password' => $plainPassword,
                'must_change_password' => true,
            ]);
            $user->syncRoles([$request->role_name]);

            $invitation = Invitation::create([
                'email' => $request->email,
                'role_name' => $request->role_name,
                'token' => Str::random(32),
                'expires_at' => now()->addDays(7),
                'is_used' => true,
            ]);

            Mail::to($request->email)->send(new InvitationMail($invitation, $plainPassword));
            DB::commit();
            return redirect()->back()->with('success', 'Invitation sent successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to send invitation.');
        }

    }

    public function accept(Request $request, $token)
    {
        // The new flow creates the account at invitation time and emails
        // the password. Any link landing here just sends the user to /login
        // where they sign in with the credentials from their email.
        return redirect('/login')->with(
            'success',
            'Connectez-vous avec le mot de passe reçu par email.'
        );
    }

    public function update(Request $request, $id)
    {
        $invitation = Invitation::findOrFail($id);

        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore(
                    optional(User::where('email', $invitation->email)->first())->id
                )->whereNull('deleted_at'),
                Rule::unique('invitations', 'email')->ignore($id)->whereNull('deleted_at'),
            ],
            'role_name' => 'required|string|exists:roles,name',
        ]);

        DB::beginTransaction();
        try {
            $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);

            $user = User::where('email', $invitation->email)->first()
                ?? new User(['name' => Str::before($request->email, '@')]);

            $user->email = $request->email;
            $user->password = $plainPassword;
            $user->must_change_password = true;
            $user->save();
            $user->syncRoles([$request->role_name]);

            $invitation->email = $request->email;
            $invitation->role_name = $request->role_name;
            $invitation->expires_at = now()->addDays(7);
            $invitation->is_used = true;
            $invitation->save();

            Mail::to($request->email)->send(new InvitationMail($invitation, $plainPassword));
            DB::commit();

            return redirect()->back()->with('success', 'Invitation updated successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to update invitation.');
        }
    }

    // delete
    public function destroy($id)
    {
        $invitation = Invitation::findOrFail($id);
        $invitation->delete();
        return redirect()->back()->with('success', 'Invitation deleted successfully.');
    }


}
