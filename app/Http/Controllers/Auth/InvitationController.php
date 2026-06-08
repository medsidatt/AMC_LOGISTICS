<?php

namespace App\Http\Controllers\Auth;

use App\Http\Concerns\AssignableRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendInvitationRequest;
use App\Http\Requests\UpdateInvitationRequest;
use App\Mail\InvitationMail;
use App\Models\Auth\Invitation;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InvitationController extends Controller
{
    use AssignableRoles;

    public function __construct()
    {
        $this->middleware('permission:invitation-list', ['only' => ['index']]);
        $this->middleware('permission:invitation-create', ['only' => ['sendInvitation']]);
        $this->middleware('permission:invitation-edit', ['only' => ['update']]);
        $this->middleware('permission:invitation-delete', ['only' => ['destroy']]);
    }

    // index
    public function index()
    {
        $invitations = Invitation::query()
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (Invitation $inv) => [
                'id' => $inv->id,
                'name' => $inv->name,
                'email' => $inv->email,
                'role_name' => $inv->role_name,
                'is_used' => $inv->is_used,
                'expires_at' => $inv->expires_at,
                'created_at' => $inv->created_at?->format('d/m/Y'),
            ]);

        $roles = $this->assignableRoleNames()->map(fn ($name) => [
            'name' => $name,
        ])->toArray();

        return Inertia::render('invitations/Index', [
            'invitations' => $invitations,
            'roles' => $roles,
        ]);
    }

    public function sendInvitation(SendInvitationRequest $request)
    {
        DB::beginTransaction();
        try {
            $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);

            // Create the account up front so the invitee can log in
            // directly with the password we email them.
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $plainPassword,
                'must_change_password' => true,
            ]);
            $user->syncRoles([$request->role_name]);

            $invitation = Invitation::create([
                'name' => $request->name,
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

    public function update(UpdateInvitationRequest $request, $id)
    {
        $invitation = Invitation::findOrFail($id);

        DB::beginTransaction();
        try {
            $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);

            $user = User::where('email', $invitation->email)->first() ?? new User();

            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = $plainPassword;
            $user->must_change_password = true;
            $user->save();
            $user->syncRoles([$request->role_name]);

            $invitation->name = $request->name;
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
