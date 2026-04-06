<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Auth\Invitation;
use App\Models\Auth\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * - Admin can assign Admin and Driver only
     */
    private function assignableRoles()
    {
        $user = auth()->user();
        $query = Role::whereIn('name', ['Super Admin', 'Admin', 'Driver']);

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

    public function accept($token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->is_used || $invitation->isExpired()) {
            return view('auth.invitations.expired', [
                'message' => 'Invalid or expired invitation.',
            ]);
        }

        return view('auth.register', ['email' => $invitation->email, 'token' => $token]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'required|numeric|digits:8',
            'password' => 'required|confirmed',
            'password_confirmation' => ['required', 'same:password'],
            'token' => 'required',
        ]);

        $invitation = Invitation::where('token', $request->token)->firstOrFail();

        if ($invitation->is_used || $invitation->isExpired()) {
            return response()->json(['message' => 'Invalid or expired invitation.'], 400);
        }

        $user = User::firstOrCreate([
            'email' => $invitation->email,
        ], [
            'name' => $request->first_name . ' ' . $request->last_name,
            'password' => bcrypt($request->password),
        ]);

        if (! empty($invitation->role_name)) {
            $role = Role::query()->where('name', $invitation->role_name)->first();
            if ($role) {
                $user->syncRoles([$role->name]);
            }
        }

        $invitation->update(['is_used' => true]);

        try {
            Auth::login($user);
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Failed to login.');
        }

        return redirect()->route('home');
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
