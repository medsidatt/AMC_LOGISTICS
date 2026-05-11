<?php

namespace App\Http\Controllers;

use App\Models\Auth\Invitation;
use App\Models\Auth\User;
use App\Support\SilentSso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

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
        $invitationToken = $request->session()->get('invitation_token');

        // Microsoft signals silent-SSO failure (or any OAuth error) via
        // ?error= on the redirect.
        if ($request->filled('error')) {
            Log::info('Microsoft OAuth callback error', [
                'error' => $request->query('error'),
                'error_description' => $request->query('error_description'),
            ]);
            SilentSso::markFailed($request);
            return $this->bounceOnFailure($request, $invitationToken);
        }

        try {
            $microsoftUser = Socialite::driver('azure')->stateless()->user();
        } catch (\Throwable $e) {
            Log::warning('Microsoft OAuth user resolution failed', ['message' => $e->getMessage()]);
            SilentSso::markFailed($request);
            return $this->bounceOnFailure($request, $invitationToken, 'La connexion Microsoft a échoué.');
        }

        $email = $microsoftUser->getEmail()
            ?? ($microsoftUser->user['mail'] ?? null)
            ?? ($microsoftUser->user['userPrincipalName'] ?? null);

        if (! $email) {
            SilentSso::markFailed($request);
            return $this->bounceOnFailure($request, $invitationToken, 'Aucune adresse email Microsoft trouvée.');
        }

        if ($invitationToken) {
            return $this->finishInvitation($request, $invitationToken, $email, $microsoftUser->getName());
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            SilentSso::markFailed($request);
            return $this->failToLogin(
                $request,
                "Aucun compte n'est associé à cette adresse Microsoft. Contactez l'administrateur."
            );
        }

        SilentSso::clearCooldown($request);
        Auth::login($user);

        return redirect()->intended(route('home'));
    }

    protected function finishInvitation(Request $request, string $token, string $microsoftEmail, ?string $microsoftName)
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || $invitation->is_used || $invitation->isExpired()) {
            $request->session()->forget('invitation_token');
            return $this->failToLogin($request, 'Cette invitation est invalide ou expirée.');
        }

        // Wrong Microsoft account active in the browser — keep the
        // invitation alive and send them back to the accept page where they
        // can re-trigger an interactive sign-in after switching account.
        if (strcasecmp($invitation->email, $microsoftEmail) !== 0) {
            return redirect("/auth/invitations/{$token}/accept")->with(
                'error',
                "Cette invitation a été émise pour {$invitation->email}, mais vous êtes connecté en tant que {$microsoftEmail}. Déconnectez-vous de Microsoft et réessayez."
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

        $request->session()->forget('invitation_token');
        SilentSso::clearCooldown($request);
        Auth::login($user);

        return redirect()->intended(route('home'));
    }

    protected function bounceOnFailure(Request $request, ?string $invitationToken, ?string $message = null)
    {
        // Mid-invitation: keep the user in that flow so they can retry from
        // the accept page (button forces an interactive sign-in).
        if ($invitationToken) {
            $redirect = redirect("/auth/invitations/{$invitationToken}/accept");
            return $message ? $redirect->with('error', $message) : $redirect;
        }

        return $this->failToLogin($request, $message);
    }

    protected function failToLogin(Request $request, ?string $message = null)
    {
        $redirect = redirect('/login');
        return $message ? $redirect->with('error', $message) : $redirect;
    }
}
