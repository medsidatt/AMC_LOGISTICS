<?php

namespace App\Http\Controllers;

use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        // Microsoft signals silent-SSO failure (or any OAuth error) via
        // ?error= on the redirect — fall back to the login form.
        if ($request->filled('error')) {
            return redirect('/login');
        }

        try {
            $microsoftUser = Socialite::driver('azure')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect('/login')->with('error', 'La connexion Microsoft a échoué.');
        }

        $email = $microsoftUser->getEmail()
            ?? ($microsoftUser->user['mail'] ?? null)
            ?? ($microsoftUser->user['userPrincipalName'] ?? null);

        $user = $email ? User::where('email', $email)->first() : null;

        if (! $user) {
            return redirect('/login')->with(
                'error',
                "Aucun compte n'est associé à cette adresse Microsoft. Contactez l'administrateur."
            );
        }

        Auth::login($user);

        return redirect()->route('home');
    }
}
