<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Auth\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
     * Roles the current user can assign:
     * - Super Admin can assign any role
     * - Admin can assign Admin, Manager and Driver only
     */
    private function assignableRoles()
    {
        $user = auth()->user();
        $query = Role::whereIn('name', ['Super Admin', 'Admin', 'Manager', 'Driver']);

        if (!$user->hasRole('Super Admin')) {
            $query->where('name', '!=', 'Super Admin');
        }

        return $query->orderBy('name')->get(['name']);
    }

    public function sendInvitation(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email|unique:invitations,email',
            'role_name' => 'required|string|exists:roles,name',
        ]);

        DB::beginTransaction();
        try {
            $token = Str::random(32);

            $invitation = Invitation::create([
                'email' => $request->email,
                'role_name' => $request->role_name,
                'token' => $token,
                'expires_at' => now()->addDays(7),
            ]);

            Mail::to($request->email)->send(new InvitationMail($invitation));
            DB::commit();
            return redirect()->back()->with('success', 'Invitation sent successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to send invitation.');
        }

    }

    public function accept(Request $request, $token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->is_used || $invitation->isExpired()) {
            return view('auth.invitations.expired', [
                'message' => 'Invalid or expired invitation.',
            ]);
        }

        // Stash the token so the Microsoft callback can finalise account
        // creation with the invited role once the user authenticates.
        $request->session()->put('invitation_token', $token);

        // First visit: try silent Microsoft SSO. If the user is already
        // signed into the tenant account matching this invitation, they're
        // straight in. Otherwise the callback bounces back here and renders
        // the manual button.
        if (! $request->session()->get('accept_sso_attempted')) {
            $request->session()->put('accept_sso_attempted', true);
            return redirect('/auth/microsoft?silent=1');
        }

        return Inertia::render('auth/AcceptInvitation', [
            'email' => $invitation->email,
            'roleName' => $invitation->role_name,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $id,
            'role_name' => 'required|string|exists:roles,name',
        ]);

        $invitation = Invitation::findOrFail($id);
        $invitation->email = $request->email;
        $invitation->role_name = $request->role_name;
        $invitation->expires_at = now()->addDays(7);
        $invitation->save();

        Mail::to($request->email)->send(new InvitationMail($invitation));

        return redirect()->back()->with('success', 'Invitation updated successfully.');

    }

    // delete
    public function destroy($id)
    {
        $invitation = Invitation::findOrFail($id);
        $invitation->delete();
        return redirect()->back()->with('success', 'Invitation deleted successfully.');
    }


}
