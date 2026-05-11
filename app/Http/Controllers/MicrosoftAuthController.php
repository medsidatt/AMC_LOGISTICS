<?php

namespace App\Http\Controllers;

use App\Models\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('azure')
            ->scopes(['openid', 'profile', 'email', 'User.Read'])
            ->redirect();
    }

    public function callback()
    {
        $microsoftUser = Socialite::driver('azure')->user();

        $email = $microsoftUser->getEmail()
            ?? ($microsoftUser->user['mail'] ?? null)
            ?? ($microsoftUser->user['userPrincipalName'] ?? null);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $microsoftUser->getName() ?? $email,
                'password' => Str::random(40),
            ]
        );

        Auth::login($user);

        return redirect()->route('home');
    }
}
