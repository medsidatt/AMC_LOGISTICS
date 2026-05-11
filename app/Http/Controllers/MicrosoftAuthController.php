<?php

namespace App\Http\Controllers;

use App\Models\Auth\Invitation;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class MicrosoftAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $driver = Socialite::driver('azure')
            ->stateless()
            ->scopes(['openid', 'profile', 'email', 'User.Read']);

        if ($request->boolean('silent')) {
            $driver->with(['prompt' => 'none']);
        }

        return $driver->redirect();
    }

    public function callback(Request $request)
    {
        // Microsoft signals silent-SSO failure (or any OAuth error) via
        // ?error= on the redirect — fall back to the login form.
        if ($request->filled('error')) {
            return $this->failToLogin($request);
        }

        try {
            $microsoftUser = Socialite::driver('azure')->stateless()->user();
        } catch (\Throwable $e) {
            return $this->failToLogin($request, 'La connexion Microsoft a échoué.');
        }

        $email = $microsoftUser->getEmail()
            ?? ($microsoftUser->user['mail'] ?? null)
            ?? ($microsoftUser->user['userPrincipalName'] ?? null);

        if (! $email) {
            return $this->failToLogin($request, 'Aucune adresse email Microsoft trouvée.');
        }

        // Invitation flow — user clicked an invite link and just authenticated.
        if ($invitationToken = $request->session()->pull('invitation_token')) {
            return $this->finishInvitation($request, $invitationToken, $email, $microsoftUser->getName());
        }

        // Login-only flow.
        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->failToLogin(
                $request,
                "Aucun compte n'est associé à cette adresse Microsoft. Contactez l'administrateur."
            );
        }

        Auth::login($user);

        return redirect()->route('home');
    }

    protected function finishInvitation(Request $request, string $token, string $microsoftEmail, ?string $microsoftName)
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || $invitation->is_used || $invitation->isExpired()) {
            return $this->failToLogin($request, 'Cette invitation est invalide ou expirée.');
        }

        if (strcasecmp($invitation->email, $microsoftEmail) !== 0) {
            return $this->failToLogin(
                $request,
                "Cette invitation a été émise pour {$invitation->email}, mais vous êtes connecté en tant que {$microsoftEmail}."
            );
        }

        $user = DB::transaction(function () use ($invitation, $microsoftName) {
            $user = User::firstOrCreate(
                ['email' => $invitation->email],
                [
                    'name' => $microsoftName ?: $invitation->email,
                    'password' => Str::random(40),
                ],
            );

            if (! empty($invitation->role_name)) {
                $user->syncRoles([$invitation->role_name]);
            }

            $invitation->update(['is_used' => true]);

            return $user;
        });

        Auth::login($user);

        return redirect()->route('home');
    }

    protected function failToLogin(Request $request, ?string $message = null)
    {
        // Prevent the /login auto-silent-SSO loop on the next request.
        $request->session()->put('sso_attempted', true);

        $redirect = redirect('/login');

        return $message ? $redirect->with('error', $message) : $redirect;
    }
}
